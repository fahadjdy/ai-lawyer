<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        // Anyone who can see the task can discuss it.
        $this->authorize('view', $task);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Comment posted.');
    }

    public function destroy(Request $request, Task $task, TaskComment $comment): RedirectResponse
    {
        abort_unless($comment->task_id === $task->id, 404);

        // The author may delete their own comment; task managers may delete any.
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->can('update', $task),
            403,
        );

        $comment->delete();

        return back()->with('success', 'Comment deleted.');
    }
}
