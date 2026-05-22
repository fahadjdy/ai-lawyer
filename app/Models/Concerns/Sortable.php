<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a safe `applySorting` scope that parses an API-style sort string
 * ("-created_at", "title") against a per-model allowlist, preventing SQL
 * injection through arbitrary order-by columns.
 */
trait Sortable
{
    /**
     * Columns permitted for sorting. Override per model.
     *
     * @return array<int, string>
     */
    public function sortableColumns(): array
    {
        return ['created_at', 'updated_at'];
    }

    public function scopeApplySorting(Builder $query, ?string $sort): Builder
    {
        $sort ??= '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $this->sortableColumns(), true)) {
            $column = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($query->getModel()->getTable().'.'.$column, $direction);
    }
}
