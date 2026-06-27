<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::with(['assignee', 'case:id,uuid,case_number,title'])
            ->when($request->filled('assigned_to_me'), fn ($q) => $q->where('assigned_to', auth()->id()))
            ->orderBy('position')
            ->get();

        // Group into Kanban columns keyed by status.
        $columns = collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $s) => [
            $s->value => [
                'label' => $s->label(),
                'color' => $s->color(),
                'tasks' => TaskResource::collection($tasks->where('status', $s)->values()),
            ],
        ]);

        return Inertia::render('tasks/Index', [
            'columns' => $columns,
            'options' => $this->formOptions(),
            'meId' => auth()->id(),
        ]);
    }

    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load(['case:id,uuid,case_number,title', 'assignee:id,uuid,name', 'creator:id,uuid,name']);

        /** @var Collection<int, Activity> $activities */
        $activities = $task->activities()->with('causer:id,uuid,name')->latest()->get();

        // Pre-resolve user / case ids referenced anywhere in the change-sets so
        // the timeline can render names instead of raw foreign keys.
        $userIds = [];
        $caseIds = [];
        foreach ($activities as $a) {
            foreach (['old', 'attributes'] as $bag) {
                $userIds[] = data_get($a->attribute_changes, "{$bag}.assigned_to");
                $caseIds[] = data_get($a->attribute_changes, "{$bag}.case_id");
            }
        }
        $users = User::whereIn('id', array_filter($userIds))->pluck('name', 'id');
        $cases = LegalCase::whereIn('id', array_filter($caseIds))->pluck('case_number', 'id');

        return Inertia::render('tasks/Show', [
            'task' => [
                'id' => $task->uuid,
                'title' => $task->title,
                'description' => $task->description,
                'case_id' => $task->case_id,
                'assigned_to' => $task->assigned_to,
                'status' => ['value' => $task->status->value, 'label' => $task->status->label(), 'color' => $task->status->color()],
                'priority' => ['value' => $task->priority->value, 'label' => $task->priority->label(), 'color' => $task->priority->color()],
                'due_at' => $task->due_at?->toIso8601String(),
                'completed_at' => $task->completed_at?->toIso8601String(),
                'is_overdue' => $task->isOverdue(),
                'case' => $task->case ? ['id' => $task->case->uuid, 'case_number' => $task->case->case_number, 'title' => $task->case->title] : null,
                'assignee' => $task->assignee ? ['name' => $task->assignee->name, 'initials' => $task->assignee->initials()] : null,
                'creator' => $task->creator?->name,
                'created_at' => $task->created_at?->toIso8601String(),
            ],
            'timeline' => $activities->map(fn (Activity $a) => [
                'id' => $a->id,
                'event' => $a->event,
                'causer' => $a->causer?->name,
                'causer_initials' => $a->causer ? $a->causer->initials() : null,
                'created_at' => $a->created_at?->toIso8601String(),
                'changes' => $a->event === 'updated' ? $this->describeChanges($a, $users, $cases) : [],
            ]),
            'options' => $this->formOptions(),
            'can' => ['manage' => auth()->user()->can('update', $task)],
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $teamId = $request->user()->team_id;

        // New tasks surface at the TOP of their column: push the column down by
        // one and insert the newcomer at position 0. (Bulk increment fires no
        // model events, so it won't spam the activity log.)
        Task::where('team_id', $teamId)
            ->where('status', $data['status'])
            ->increment('position');

        $data['position'] = 0;
        $data['created_by'] = $request->user()->id;
        $data['completed_at'] = $data['status'] === TaskStatus::Done->value ? now() : null;

        $task = Task::create($data);

        $this->notifyAssignee($task);

        return back()->with('success', 'Task created.');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();

        // Keep completed_at in sync when the status crosses the Done boundary.
        if (array_key_exists('status', $data)) {
            if ($data['status'] === TaskStatus::Done->value && ! $task->completed_at) {
                $data['completed_at'] = now();
            } elseif ($data['status'] !== TaskStatus::Done->value) {
                $data['completed_at'] = null;
            }
        }

        $previousAssignee = $task->assigned_to;

        $task->update($data);

        // Notify the assignee only when the task is (re)assigned to someone new.
        if (array_key_exists('assigned_to', $data)) {
            $this->notifyAssignee($task, $previousAssignee);
        }

        // A bare drag/quick-move shouldn't flash a toast; an edit should.
        return $request->boolean('silent')
            ? back()
            : back()->with('success', 'Task updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back()->with('success', 'Task deleted.');
    }

    /**
     * Persist a drag-and-drop reorder. The client sends the target column's
     * status and the full ordered list of task UUIDs in that column — covering
     * both vertical reordering within a column and a cross-column move. Each
     * task gets its new position; the moved card also adopts the column status.
     */
    public function reorder(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionType::ManageTasks->value), 403);

        $teamId = $request->user()->team_id;

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'ids' => ['required', 'array'],
            'ids.*' => ['string', Rule::exists('tasks', 'uuid')->where('team_id', $teamId)],
        ]);

        $status = $validated['status'];

        DB::transaction(function () use ($validated, $status, $teamId): void {
            $tasks = Task::where('team_id', $teamId)
                ->whereIn('uuid', $validated['ids'])
                ->get()
                ->keyBy('uuid');

            foreach ($validated['ids'] as $index => $uuid) {
                $task = $tasks->get($uuid);
                if (! $task) {
                    continue;
                }

                $task->position = $index;
                $task->status = $status;

                // Keep completed_at in sync as cards cross the Done boundary.
                if ($status === TaskStatus::Done->value && ! $task->completed_at) {
                    $task->completed_at = now();
                } elseif ($status !== TaskStatus::Done->value) {
                    $task->completed_at = null;
                }

                $task->save();
            }
        });

        return back();
    }

    /**
     * Notify the task's assignee when it is (re)assigned — but not when a user
     * assigns a task to themselves, and not on a no-op re-save. Failures are
     * logged and swallowed so notification issues never break the request.
     */
    private function notifyAssignee(Task $task, ?int $previousAssignee = null): void
    {
        $assignee = $task->assigned_to;

        if (! $assignee || $assignee === $previousAssignee || $assignee === auth()->id()) {
            return;
        }

        try {
            User::find($assignee)?->notify(new TaskAssignedNotification($task));
        } catch (\Throwable $e) {
            Log::warning('Task assignment notification failed: '.$e->getMessage(), ['task_id' => $task->id]);
        }
    }

    /**
     * Cases & assignable users for the create/edit form selects.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => TaskStatus::options(),
            'priorities' => TaskPriority::options(),
            'cases' => LegalCase::query()
                ->orderBy('title')
                ->get(['id', 'uuid', 'case_number', 'title'])
                ->map(fn (LegalCase $c) => ['id' => $c->id, 'name' => $c->case_number ? "{$c->case_number} — {$c->title}" : $c->title]),
            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
        ];
    }

    /**
     * Turn an activity's raw old/new property bags into a human-friendly list
     * of field changes (e.g. Status: "To Do" → "In Progress").
     *
     * @param  Collection<int|string, string>  $users
     * @param  Collection<int|string, string>  $cases
     * @return array<int, array<string, ?string>>
     */
    private function describeChanges(Activity $activity, Collection $users, Collection $cases): array
    {
        // Field-level diffs live in `attribute_changes` (spatie v5); `properties`
        // is reserved for custom properties.
        $old = (array) data_get($activity->attribute_changes, 'old', []);
        $new = (array) data_get($activity->attribute_changes, 'attributes', []);

        $labels = [
            'title' => 'Title',
            'status' => 'Status',
            'priority' => 'Priority',
            'assigned_to' => 'Assignee',
            'case_id' => 'Case',
            'due_at' => 'Due date',
        ];

        $changes = [];
        foreach ($new as $field => $value) {
            if (! isset($labels[$field])) {
                continue;
            }
            $changes[] = [
                'label' => $labels[$field],
                'from' => $this->friendlyValue($field, $old[$field] ?? null, $users, $cases),
                'to' => $this->friendlyValue($field, $value, $users, $cases),
            ];
        }

        return $changes;
    }

    /**
     * @param  Collection<int|string, string>  $users
     * @param  Collection<int|string, string>  $cases
     */
    private function friendlyValue(string $field, mixed $value, Collection $users, Collection $cases): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'status' => TaskStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'priority' => TaskPriority::tryFrom((string) $value)?->label() ?? (string) $value,
            'assigned_to' => $users->get($value) ?? "User #{$value}",
            'case_id' => $cases->get($value) ?? "Case #{$value}",
            'due_at' => Carbon::parse((string) $value)->format('d M Y, H:i'),
            default => (string) $value,
        };
    }
}
