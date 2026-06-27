<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CaseCreated;
use App\Notifications\CaseAssignedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Reacts to a new case by notifying the lead lawyer and assignees. Runs
 * synchronously — this deployment has no queue worker, so deferring delivery
 * would silently drop notifications. Failures (e.g. SMTP down) are logged and
 * swallowed so notification delivery never breaks case creation.
 */
class SendCaseAssignmentNotifications
{
    public function handle(CaseCreated $event): void
    {
        $case = $event->case;

        $recipients = $case->assignees()->get();

        if ($case->leadLawyer && ! $recipients->contains('id', $case->leadLawyer->id)) {
            $recipients->push($case->leadLawyer);
        }

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new CaseAssignedNotification($case));
        } catch (\Throwable $e) {
            Log::warning('Case assignment notification failed: '.$e->getMessage(), ['case_id' => $case->id]);
        }
    }
}
