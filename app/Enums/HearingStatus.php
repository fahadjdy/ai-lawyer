<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum HearingStatus: string
{
    use HasEnumHelpers;

    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Adjourned = 'adjourned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::Completed => 'emerald',
            self::Adjourned => 'amber',
            self::Cancelled => 'rose',
        };
    }
}
