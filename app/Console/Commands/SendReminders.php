<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\HearingStatus;
use App\Enums\TaskStatus;
use App\Models\Hearing;
use App\Models\Task;
use App\Notifications\ReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Sends deadline reminders for hearings & tasks due in the next 24 hours.
 * Runs across all firms (no team context in console), so it is scheduled
 * once daily. Requires a cron entry running `php artisan schedule:run`.
 */
class SendReminders extends Command
{
    protected $signature = 'app:send-reminders';

    protected $description = 'Notify lawyers of hearings & tasks due in the next 24 hours';

    public function handle(): int
    {
        $from = now();
        $to = now()->addDay();

        $hearings = Hearing::with(['case.leadLawyer', 'case.assignees'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->where('status', HearingStatus::Scheduled->value)
            ->get();

        foreach ($hearings as $hearing) {
            $case = $hearing->case;
            if (! $case) {
                continue;
            }

            $recipients = $case->assignees
                ->when($case->leadLawyer, fn ($c) => $c->push($case->leadLawyer))
                ->filter()
                ->unique('id');

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ReminderNotification(
                    'hearing.reminder',
                    "Upcoming hearing: {$case->case_number}",
                    "Hearing for \"{$case->title}\" on ".$hearing->scheduled_at->format('d M Y, H:i').'.',
                    '/hearings',
                ));
            }
        }

        $tasks = Task::with('assignee')
            ->whereNotNull('assigned_to')
            ->whereBetween('due_at', [$from, $to])
            ->where('status', '!=', TaskStatus::Done->value)
            ->get();

        foreach ($tasks as $task) {
            if ($task->assignee) {
                $task->assignee->notify(new ReminderNotification(
                    'task.reminder',
                    "Task due soon: {$task->title}",
                    'Due '.$task->due_at->format('d M Y, H:i').'.',
                    '/tasks',
                ));
            }
        }

        $this->info("Reminders sent: {$hearings->count()} hearing(s), {$tasks->count()} task(s).");

        return self::SUCCESS;
    }
}
