<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum CaseStatus: string
{
    use HasEnumHelpers;

    case Intake = 'intake';
    case Open = 'open';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Won = 'won';
    case Lost = 'lost';
    case Settled = 'settled';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Intake => 'Intake',
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::OnHold => 'On Hold',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Settled => 'Settled',
            self::Closed => 'Closed',
        };
    }

    /**
     * Tailwind-friendly semantic color token consumed by the front-end badge component.
     */
    public function color(): string
    {
        return match ($this) {
            self::Intake => 'slate',
            self::Open, self::InProgress => 'blue',
            self::OnHold => 'amber',
            self::Won, self::Settled => 'emerald',
            self::Lost => 'rose',
            self::Closed => 'zinc',
        };
    }

    /**
     * Statuses that are considered active (still requiring lawyer attention).
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Intake, self::Open, self::InProgress, self::OnHold];
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost, self::Settled, self::Closed], true);
    }
}
