<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums so they can be safely used across
 * validation rules, API resources and the Inertia/Vue front-end without
 * duplicating value/label/option mapping logic.
 */
trait HasEnumHelpers
{
    /**
     * All raw backing values — handy for `Rule::in(...)` validation.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => (string) $case->value, self::cases());
    }

    /**
     * All case names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Front-end friendly option list: [{ value, label, color }].
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => [
            'value' => (string) $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }

    /**
     * Resolve an enum from a nullable value without throwing.
     */
    public static function tryFromValue(int|string|null $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
