<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskItemController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $task->items()->create([
            'title' => $validated['title'],
            'position' => (int) $task->items()->max('position') + 1,
            'created_by' => $request->user()->id,
        ]);

        return back();
    }

    public function update(Request $request, Task $task, TaskItem $item): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($item->task_id === $task->id, 404);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        $item->update($validated);

        return back();
    }

    public function destroy(Task $task, TaskItem $item): RedirectResponse
    {
        $this->authorize('update', $task);
        abort_unless($item->task_id === $task->id, 404);

        $item->delete();

        return back();
    }
}
