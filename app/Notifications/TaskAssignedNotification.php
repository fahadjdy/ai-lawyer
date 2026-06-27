<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Task $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->prefersEmail() ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Task assigned: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been assigned a task: \"{$this->task->title}\".");

        if ($this->task->due_at) {
            $mail->line('Due: '.$this->task->due_at->format('d M Y, H:i'));
        }

        return $mail->action('View Tasks', url('/tasks'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task.assigned',
            'task_uuid' => $this->task->uuid,
            'title' => $this->task->title,
            'due_at' => $this->task->due_at?->toIso8601String(),
            'url' => '/tasks',
            'message' => "You were assigned the task \"{$this->task->title}\".",
        ];
    }
}
