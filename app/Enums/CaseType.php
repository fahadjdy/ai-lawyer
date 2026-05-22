<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum CaseType: string
{
    use HasEnumHelpers;

    case Civil = 'civil';
    case Criminal = 'criminal';
    case Corporate = 'corporate';
    case Family = 'family';
    case Property = 'property';
    case Labour = 'labour';
    case Taxation = 'taxation';
    case Constitutional = 'constitutional';
    case Arbitration = 'arbitration';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Civil => 'Civil',
            self::Criminal => 'Criminal',
            self::Corporate => 'Corporate',
            self::Family => 'Family',
            self::Property => 'Property',
            self::Labour => 'Labour & Employment',
            self::Taxation => 'Taxation',
            self::Constitutional => 'Constitutional',
            self::Arbitration => 'Arbitration',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return 'slate';
    }
}
