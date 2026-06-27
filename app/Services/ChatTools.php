<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\LegalSection;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The agentic toolbox the chat assistant can call to work with the firm's live
 * data: searching and opening cases, listing upcoming hearings, finding clients,
 * looking up statute sections, and creating tasks. Every query runs server-side
 * and is automatically team-scoped; the one write action (create_task) is gated
 * by the same permission the UI enforces.
 *
 * Each tool returns ['data' => mixed, 'citations' => array] — the data is fed
 * back to the model as the tool result, the citations surface in the UI.
 *
 * The analytics tools (get_statistics / get_user_caseload / compare_cases /
 * firm_overview) expose firm-wide numbers and are therefore gated to admins
 * (the settings.manage permission), mirroring how create_task is gated.
 */
class ChatTools
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    /**
     * Anthropic tool definitions advertised to the model.
     *
     * @return array<int, array<string, mixed>>
     */
    public function specs(): array
    {
        return [
            $this->fn('search_cases', 'Search the firm\'s cases by keyword (matches case number, title, court, or opposing party). Use when the user refers to a matter without attaching it.', [
                'query' => ['type' => 'string', 'description' => 'Keywords, a case number, a party name, or court.'],
            ], ['query']),

            $this->fn('get_case', 'Get the full details and tracking history of one case by its case number.', [
                'case_number' => ['type' => 'string', 'description' => 'The exact case number, e.g. CR-2024-001.'],
            ], ['case_number']),

            // `days` is declared as a string (not integer): the model often emits
            // numbers as strings — we coerce to int when executing.
            $this->fn('list_upcoming_hearings', 'List the firm\'s scheduled hearings within the next N days. Use for questions about the calendar / what is coming up.', [
                'days' => ['type' => 'string', 'description' => 'How many days ahead to look, as a number. Defaults to 14.'],
            ], []),

            $this->fn('find_clients', 'Search the firm\'s clients by name, company, email or phone.', [
                'query' => ['type' => 'string', 'description' => 'Client name, company, email or phone.'],
            ], ['query']),

            $this->fn('search_legal_sections', 'Look up Indian statute sections in the firm\'s legal library (IPC, BNS, CrPC, Evidence Act, etc.) by keyword or section number.', [
                'query' => ['type' => 'string', 'description' => 'Topic keywords or a section number, e.g. "cheating" or "420".'],
            ], ['query']),

            $this->fn('create_task', 'Create a task / to-do for the firm. Only call this when the user clearly asks to create, add or remind them of a task.', [
                'title' => ['type' => 'string', 'description' => 'Short task title.'],
                'description' => ['type' => 'string', 'description' => 'Optional details.'],
                'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'description' => 'Defaults to medium.'],
                'due_date' => ['type' => 'string', 'description' => 'Optional due date, e.g. 2026-06-10 or "next Friday".'],
                'case_number' => ['type' => 'string', 'description' => 'Optional case number to link the task to.'],
            ], ['title']),

            // --- Analytics (admin only) ---------------------------------------
            $this->fn('get_statistics', 'Firm-wide analytics: COUNT records, optionally broken down by a dimension and narrowed by filters. Use for any "how many / total / count / kitne / breakdown / distribution / per lawyer / by status / by month / this month" question about the firm\'s data. Admin only.', [
                'entity' => ['type' => 'string', 'enum' => ['cases', 'tasks', 'hearings', 'clients', 'users'], 'description' => 'What to count.'],
                'group_by' => ['type' => 'string', 'description' => 'Optional dimension to break the count down by. cases: status, type, priority, lawyer, client, court, month. tasks: status, priority, assignee. hearings: status, month, judge. clients: type, city, state. users: designation, status.'],
                'status' => ['type' => 'string', 'description' => 'Optional status filter (use the entity\'s own status values, e.g. cases: open/in_progress/closed…; tasks: todo/in_progress/review/done; hearings: scheduled/completed/adjourned/cancelled).'],
                'type' => ['type' => 'string', 'description' => 'Optional type filter — case type (civil, criminal, family…) or client type.'],
                'priority' => ['type' => 'string', 'description' => 'Optional priority filter for cases (low/medium/high/critical) or tasks (low/medium/high/urgent).'],
                'lawyer' => ['type' => 'string', 'description' => 'Optional: restrict cases to this lead lawyer (name or part of it).'],
                'period' => ['type' => 'string', 'enum' => ['today', 'this_week', 'this_month', 'last_30_days', 'this_quarter', 'this_year', 'last_year', 'all'], 'description' => 'Optional date window (cases/clients/tasks by created date, hearings by scheduled date). Defaults to all time.'],
            ], ['entity']),

            $this->fn('get_user_caseload', 'Per-user workload across the firm: how many cases each user leads and is assigned to, plus their open tasks. Use for "how many cases does <person> have" or "caseload per lawyer". Admin only.', [
                'user' => ['type' => 'string', 'description' => 'Optional user name (or part of it) to focus on. Omit to list every team member.'],
            ], []),

            $this->fn('compare_cases', 'Compare two or more specific cases side by side — status, type, client, lead lawyer, next hearing and the counts of hearings/documents/evidence/tasks/notes. Use when the user names multiple cases to combine or compare. Admin only.', [
                'case_numbers' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'The case numbers to compare, e.g. ["CR-2024-001", "CV-2024-014"].'],
            ], ['case_numbers']),

            $this->fn('firm_overview', 'A high-level snapshot of the whole firm: total & active cases (with their status/type mix), client count, upcoming hearings, open & overdue tasks, and team size. Use for broad "give me an overview / summary of the system" questions. Admin only.', [], []),
        ];
    }

    /**
     * Execute a tool call.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    public function execute(string $name, array $args, User $user): array
    {
        try {
            return match ($name) {
                'search_cases' => $this->searchCases((string) ($args['query'] ?? '')),
                'get_case' => $this->getCase((string) ($args['case_number'] ?? '')),
                'list_upcoming_hearings' => $this->upcomingHearings((int) ($args['days'] ?? 14)),
                'find_clients' => $this->findClients((string) ($args['query'] ?? '')),
                'search_legal_sections' => $this->searchSections((string) ($args['query'] ?? '')),
                'create_task' => $this->createTask($args, $user),
                'get_statistics' => $this->statistics($args, $user),
                'get_user_caseload' => $this->caseload($args, $user),
                'compare_cases' => $this->compareCases($args, $user),
                'firm_overview' => $this->firmOverview($user),
                default => ['data' => ['error' => "Unknown tool: {$name}"], 'citations' => []],
            };
        } catch (Throwable $e) {
            return ['data' => ['error' => 'The tool failed: '.$e->getMessage()], 'citations' => []];
        }
    }

    /**
     * A short human label describing a tool call, shown live in the UI.
     *
     * @param  array<string, mixed>  $args
     */
    public function statusLabel(string $name, array $args): string
    {
        return match ($name) {
            'search_cases' => 'Searching cases'.$this->forQuery($args['query'] ?? null),
            'get_case' => 'Opening case '.($args['case_number'] ?? ''),
            'list_upcoming_hearings' => 'Checking upcoming hearings',
            'find_clients' => 'Looking up clients'.$this->forQuery($args['query'] ?? null),
            'search_legal_sections' => 'Searching the legal library'.$this->forQuery($args['query'] ?? null),
            'create_task' => 'Creating a task',
            'get_statistics' => 'Crunching the numbers'.(isset($args['entity']) ? ' ('.$args['entity'].')' : ''),
            'get_user_caseload' => 'Tallying caseloads',
            'compare_cases' => 'Comparing cases',
            'firm_overview' => 'Building a firm overview',
            default => 'Working',
        };
    }

    private function forQuery(mixed $q): string
    {
        $q = trim((string) $q);

        return $q === '' ? '' : ' for “'.$q.'”';
    }

    /**
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function searchCases(string $query): array
    {
        $cases = LegalCase::query()
            ->search($query)
            ->with('client:id,name')
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $data = $cases->map(fn (LegalCase $c): array => [
            'case_number' => $c->case_number,
            'title' => $c->title,
            'status' => $c->status?->label(),
            'type' => $c->case_type?->label(),
            'client' => $c->client?->name,
            'opposing_party' => $c->opposing_party,
            'next_hearing_at' => $c->next_hearing_at?->toDateString(),
        ])->all();

        return ['data' => ['count' => count($data), 'cases' => $data], 'citations' => $this->caseCitations($cases)];
    }

    /**
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function getCase(string $caseNumber): array
    {
        $case = LegalCase::with('client:id,name', 'events')
            ->where('case_number', $caseNumber)
            ->first();

        if ($case === null) {
            return ['data' => ['error' => "No case found with number {$caseNumber}."], 'citations' => []];
        }

        return [
            'data' => [
                'case_number' => $case->case_number,
                'title' => $case->title,
                'status' => $case->status?->label(),
                'type' => $case->case_type?->label(),
                'priority' => $case->priority?->label(),
                'client' => $case->client?->name,
                'court_name' => $case->court_name,
                'opposing_party' => $case->opposing_party,
                'next_hearing_at' => $case->next_hearing_at?->toDayDateTimeString(),
                'description' => $case->description,
                'tracking_history' => $case->trackingHistory(),
            ],
            'citations' => $this->caseCitations(collect([$case])),
        ];
    }

    /**
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function upcomingHearings(int $days): array
    {
        $days = max(1, min($days, 120));
        $from = Carbon::now();
        $to = Carbon::now()->addDays($days);

        $hearings = Hearing::with('case:id,uuid,case_number,title')
            ->whereBetween('scheduled_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->orderBy('scheduled_at')
            ->limit(25)
            ->get();

        $data = $hearings->map(fn (Hearing $h): array => [
            'when' => $h->scheduled_at?->toDayDateTimeString(),
            'case_number' => $h->case?->case_number,
            'case_title' => $h->case?->title,
            'purpose' => $h->purpose,
            'court_room' => $h->court_room,
            'judge' => $h->judge_name,
            'status' => $h->status?->label(),
        ])->all();

        $citations = $hearings->map(fn (Hearing $h) => $h->case)->filter()->unique('id')
            ->map(fn ($c): array => [
                'type' => 'case',
                'label' => $c->case_number ?: 'Case',
                'title' => $c->title,
                'url' => '/cases/'.$c->uuid,
            ])->values()->all();

        return ['data' => ['window_days' => $days, 'count' => count($data), 'hearings' => $data], 'citations' => $citations];
    }

    /**
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function findClients(string $query): array
    {
        $clients = Client::query()->search($query)->limit(8)->get();

        $data = $clients->map(fn (Client $c): array => [
            'name' => $c->name,
            'company' => $c->company,
            'email' => $c->email,
            'phone' => $c->phone,
            'city' => $c->city,
            'type' => $c->type?->label(),
        ])->all();

        return ['data' => ['count' => count($data), 'clients' => $data], 'citations' => []];
    }

    /**
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function searchSections(string $query): array
    {
        $sections = LegalSection::query()
            ->where(function (Builder $q) use ($query): void {
                $q->where('act_name', 'like', "%{$query}%")
                    ->orWhere('section_number', 'like', "%{$query}%")
                    ->orWhere('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(8)
            ->get();

        $data = $sections->map(fn (LegalSection $s): array => [
            'act' => $s->act_name,
            'section' => $s->section_number,
            'title' => $s->title,
            'summary' => $s->description,
        ])->all();

        $citations = $sections->map(fn (LegalSection $s): array => [
            'type' => 'section',
            'label' => '§'.$s->section_number,
            'title' => $s->act_name.' — '.$s->title,
            'url' => '/legal-notebook',
        ])->all();

        return ['data' => ['count' => count($data), 'sections' => $data], 'citations' => $citations];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function createTask(array $args, User $user): array
    {
        if (! $user->can('tasks.manage')) {
            return ['data' => ['error' => 'You do not have permission to create tasks.'], 'citations' => []];
        }

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '') {
            return ['data' => ['error' => 'A task title is required.'], 'citations' => []];
        }

        $priority = TaskPriority::tryFrom((string) ($args['priority'] ?? 'medium')) ?? TaskPriority::Medium;

        $dueAt = null;
        if (! empty($args['due_date'])) {
            try {
                $dueAt = Carbon::parse((string) $args['due_date']);
            } catch (Throwable) {
                $dueAt = null;
            }
        }

        $caseId = null;
        if (! empty($args['case_number'])) {
            $caseId = LegalCase::where('case_number', (string) $args['case_number'])->value('id');
        }

        // Mirror TaskController@store: new tasks land at the top of the To-Do column.
        Task::where('status', TaskStatus::Todo->value)->increment('position');

        $task = Task::create([
            'title' => mb_substr($title, 0, 255),
            'description' => isset($args['description']) ? mb_substr((string) $args['description'], 0, 10000) : null,
            'status' => TaskStatus::Todo->value,
            'priority' => $priority->value,
            'due_at' => $dueAt,
            'case_id' => $caseId,
            'position' => 0,
            'created_by' => $user->id,
        ]);

        return [
            'data' => [
                'created' => true,
                'title' => $task->title,
                'priority' => $priority->label(),
                'due_at' => $dueAt?->toDayDateTimeString(),
                'linked_case' => $caseId !== null ? (string) $args['case_number'] : null,
                'message' => 'Task created and added to the To-Do board.',
            ],
            'citations' => [[
                'type' => 'task',
                'label' => 'Task',
                'title' => $task->title,
                'url' => '/tasks',
            ]],
        ];
    }

    /**
     * Firm-wide counts/breakdowns. Admin only.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function statistics(array $args, User $user): array
    {
        if (! $user->can('settings.manage')) {
            return $this->adminOnly();
        }

        $filters = array_filter([
            'status' => isset($args['status']) ? (string) $args['status'] : null,
            'type' => isset($args['type']) ? (string) $args['type'] : null,
            'priority' => isset($args['priority']) ? (string) $args['priority'] : null,
            'lawyer' => isset($args['lawyer']) ? (string) $args['lawyer'] : null,
            'period' => isset($args['period']) ? (string) $args['period'] : null,
        ], static fn (?string $v): bool => $v !== null && $v !== '' && $v !== 'all');

        $data = $this->analytics->aggregate(
            (string) ($args['entity'] ?? 'cases'),
            isset($args['group_by']) && $args['group_by'] !== '' ? (string) $args['group_by'] : null,
            $filters,
        );

        return ['data' => $data, 'citations' => []];
    }

    /**
     * Per-user workload. Admin only.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function caseload(array $args, User $user): array
    {
        if (! $user->can('settings.manage')) {
            return $this->adminOnly();
        }

        return ['data' => $this->analytics->userCaseload(isset($args['user']) ? (string) $args['user'] : null), 'citations' => []];
    }

    /**
     * Side-by-side case comparison. Admin only.
     *
     * @param  array<string, mixed>  $args
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function compareCases(array $args, User $user): array
    {
        if (! $user->can('settings.manage')) {
            return $this->adminOnly();
        }

        $numbers = $args['case_numbers'] ?? [];
        // The model usually sends an array, but tolerate a comma-separated string.
        if (is_string($numbers)) {
            $numbers = preg_split('/\s*,\s*/', trim($numbers), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        $numbers = is_array($numbers) ? $numbers : [];

        $data = $this->analytics->compareCases($numbers);

        $citations = [];
        if (isset($data['cases']) && is_array($data['cases'])) {
            $found = LegalCase::whereIn('case_number', array_column($data['cases'], 'case_number'))
                ->get(['uuid', 'case_number', 'title']);
            $citations = $this->caseCitations($found);
        }

        return ['data' => $data, 'citations' => $citations];
    }

    /**
     * Firm snapshot. Admin only.
     *
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function firmOverview(User $user): array
    {
        if (! $user->can('settings.manage')) {
            return $this->adminOnly();
        }

        return ['data' => $this->analytics->overview(), 'citations' => []];
    }

    /**
     * @return array{data: mixed, citations: array<int, array<string, mixed>>}
     */
    private function adminOnly(): array
    {
        return ['data' => ['error' => 'Only firm admins can view firm-wide analytics — you do not have the required permission.'], 'citations' => []];
    }

    /**
     * @param  Collection<int, LegalCase>  $cases
     * @return array<int, array<string, mixed>>
     */
    private function caseCitations($cases): array
    {
        return $cases->map(fn (LegalCase $c): array => [
            'type' => 'case',
            'label' => $c->case_number ?: 'Case',
            'title' => $c->title,
            'url' => '/cases/'.$c->uuid,
        ])->all();
    }

    /**
     * Build a single Anthropic tool definition.
     *
     * @param  array<string, mixed>  $properties
     * @param  array<int, string>  $required
     * @return array<string, mixed>
     */
    private function fn(string $name, string $description, array $properties, array $required): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'input_schema' => [
                'type' => 'object',
                // Cast to object so an empty property set encodes as `{}` (a valid
                // JSON-Schema object) rather than `[]` — e.g. for firm_overview.
                'properties' => (object) $properties,
                'required' => $required,
            ],
        ];
    }
}
