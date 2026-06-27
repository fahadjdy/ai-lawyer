<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Hearing;
use App\Models\LegalCase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HearingScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Hearing $hearing,
        private readonly LegalCase $case,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->prefersEmail() ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Hearing scheduled: {$this->case->case_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A hearing has been scheduled for case \"{$this->case->title}\".")
            ->line('When: '.$this->hearing->scheduled_at->format('d M Y, H:i'))
            ->line($this->hearing->purpose ? "Purpose: {$this->hearing->purpose}" : '')
            ->action('View Hearings', url('/hearings'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'hearing.scheduled',
            'case_uuid' => $this->case->uuid,
            'case_number' => $this->case->case_number,
            'scheduled_at' => $this->hearing->scheduled_at->toIso8601String(),
            'url' => '/hearings',
            'message' => "Hearing scheduled for {$this->case->case_number} on ".$this->hearing->scheduled_at->format('d M Y, H:i').'.',
        ];
    }
}
