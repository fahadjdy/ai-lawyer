<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
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
            'options' => [
                'statuses' => TaskStatus::options(),
                'priorities' => TaskPriority::options(),
            ],
        ]);
    }
}
