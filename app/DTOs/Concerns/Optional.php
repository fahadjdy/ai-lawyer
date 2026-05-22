<?php

declare(strict_types=1);

namespace App\DTOs\Concerns;

/**
 * Sentinel representing "not provided" in a DTO, distinct from an explicit
 * null. Lets partial-update DTOs differentiate "set to null" from "leave as-is".
 */
final class Optional
{
    private static ?self $instance = null;

    public static function create(): self
    {
        return self::$instance ??= new self;
    }
}
