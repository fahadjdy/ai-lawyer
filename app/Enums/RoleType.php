<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * Canonical role slugs seeded into Spatie's roles table. Keeping them in an
 * enum gives us a single source of truth for guards, seeders and policies.
 */
enum RoleType: string
{
    use HasEnumHelpers;

    case FirmOwner = 'firm_owner';
    case Partner = 'partner';
    case Associate = 'associate';
    case Paralegal = 'paralegal';
    case Clerk = 'clerk';

    public function label(): string
    {
        return match ($this) {
            self::FirmOwner => 'Firm Owner',
            self::Partner => 'Partner',
            self::Associate => 'Associate',
            self::Paralegal => 'Paralegal',
            self::Clerk => 'Clerk',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FirmOwner => 'violet',
            self::Partner => 'blue',
            self::Associate => 'emerald',
            self::Paralegal => 'amber',
            self::Clerk => 'slate',
        };
    }

    /**
     * Roles that may manage the firm (members, billing, settings).
     *
     * @return array<int, self>
     */
    public static function administrative(): array
    {
        return [self::FirmOwner, self::Partner];
    }
}
