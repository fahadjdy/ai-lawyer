<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LegalCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CaseAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly LegalCase $case) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New case assigned: {$this->case->case_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned to case \"{$this->case->title}\".")
            ->line("Case number: {$this->case->case_number}")
            ->action('View Case', url("/cases/{$this->case->uuid}"))
            ->line('Please review the case details at your earliest convenience.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'case.assigned',
            'case_uuid' => $this->case->uuid,
            'case_number' => $this->case->case_number,
            'title' => $this->case->title,
            'message' => "You were assigned to {$this->case->case_number}.",
        ];
    }
}
