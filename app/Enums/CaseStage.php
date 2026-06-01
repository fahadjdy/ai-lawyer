<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * The lifecycle stages a case moves through. Used by the Case Tracking timeline
 * to mark where each update sits — and where the applicable sections changed.
 */
enum CaseStage: string
{
    use HasEnumHelpers;

    case Complaint = 'complaint';
    case Investigation = 'investigation';
    case Chargesheet = 'chargesheet';
    case ChargesFramed = 'charges_framed';
    case Trial = 'trial';
    case Arguments = 'arguments';
    case Judgment = 'judgment';
    case Appeal = 'appeal';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Complaint => 'FIR / Complaint',
            self::Investigation => 'Under Investigation',
            self::Chargesheet => 'Charge Sheet Filed',
            self::ChargesFramed => 'Charges Framed',
            self::Trial => 'Trial / Evidence',
            self::Arguments => 'Final Arguments',
            self::Judgment => 'Judgment',
            self::Appeal => 'Appeal',
            self::Closed => 'Closed / Disposed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Complaint => 'blue',
            self::Investigation => 'amber',
            self::Chargesheet => 'violet',
            self::ChargesFramed => 'indigo',
            self::Trial => 'blue',
            self::Arguments => 'amber',
            self::Judgment => 'emerald',
            self::Appeal => 'rose',
            self::Closed => 'slate',
        };
    }
}
