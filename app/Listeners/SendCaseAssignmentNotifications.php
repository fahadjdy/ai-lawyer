<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CaseCreated;
use App\Notifications\CaseAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Reacts to a new case by notifying the lead lawyer and assignees. Implements
 * ShouldQueue so notification fan-out never blocks the HTTP request.
 */
class SendCaseAssignmentNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(CaseCreated $event): void
    {
        $case = $event->case;

        $recipients = $case->assignees()->get();

        if ($case->leadLawyer && ! $recipients->contains('id', $case->leadLawyer->id)) {
            $recipients->push($case->leadLawyer);
        }

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new CaseAssignedNotification($case));
        }
    }
}
