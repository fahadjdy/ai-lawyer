<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum TaskStatus: string
{
    use HasEnumHelpers;

    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Review => 'In Review',
            self::Done => 'Done',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'slate',
            self::InProgress => 'blue',
            self::Review => 'amber',
            self::Done => 'emerald',
        };
    }

    public function isComplete(): bool
    {
        return $this === self::Done;
    }
}
