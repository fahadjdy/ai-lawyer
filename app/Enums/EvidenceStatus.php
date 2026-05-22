<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum EvidenceStatus: string
{
    use HasEnumHelpers;

    case Collected = 'collected';
    case UnderReview = 'under_review';
    case Admitted = 'admitted';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Collected => 'Collected',
            self::UnderReview => 'Under Review',
            self::Admitted => 'Admitted',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Collected => 'blue',
            self::UnderReview => 'amber',
            self::Admitted => 'emerald',
            self::Rejected => 'rose',
            self::Archived => 'zinc',
        };
    }
}
