<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum CasePriority: string
{
    use HasEnumHelpers;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'slate',
            self::Medium => 'blue',
            self::High => 'amber',
            self::Critical => 'rose',
        };
    }

    /**
     * Weight used for sorting / SLA calculations (higher = more urgent).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
