<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum ClientType: string
{
    use HasEnumHelpers;

    case Individual = 'individual';
    case Organization = 'organization';
    case Government = 'government';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Individual => 'blue',
            self::Organization => 'violet',
            self::Government => 'amber',
        };
    }
}
