<?php

declare(strict_types=1);

namespace App\DTOs\Concerns;

/**
 * Helper for DTOs: strip keys whose value is the {@see Optional} sentinel so a
 * partial update never overwrites columns the caller didn't intend to change.
 */
trait FiltersNullValues
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withoutOptional(array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn (mixed $value): bool => ! $value instanceof Optional,
        );
    }
}
