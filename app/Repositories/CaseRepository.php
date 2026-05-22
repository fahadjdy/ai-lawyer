<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<LegalCase>
 */
class CaseRepository extends BaseRepository
{
    protected function model(): string
    {
        return LegalCase::class;
    }

    protected function with(): array
    {
        // Eager load to avoid N+1 when rendering list/detail tables.
        return ['client:id,uuid,name,company', 'leadLawyer:id,uuid,name'];
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['search'] ?? null)
            ->status($filters['status'] ?? null)
            ->priority($filters['priority'] ?? null)
            ->when(
                isset($filters['client_id']),
                fn (Builder $q) => $q->where('client_id', $filters['client_id']),
            )
            ->when(
                isset($filters['case_type']),
                fn (Builder $q) => $q->where('case_type', $filters['case_type']),
            )
            ->when(
                ($filters['only_active'] ?? false),
                fn (Builder $q) => $q->active(),
            )
            ->when(
                isset($filters['assigned_to']),
                fn (Builder $q) => $q->whereHas('assignees', fn (Builder $a) => $a->where('users.id', $filters['assigned_to'])),
            )
            ->applySorting($filters['sort'] ?? '-created_at');
    }

    /**
     * Detail view with the full relationship graph for a case page.
     */
    public function findForDisplay(string $uuid): LegalCase
    {
        return LegalCase::query()
            ->with([
                'client',
                'leadLawyer:id,uuid,name,designation',
                'assignees:id,uuid,name,designation',
                'hearings' => fn ($q) => $q->orderByDesc('scheduled_at'),
                'tasks' => fn ($q) => $q->orderBy('position'),
                'documents' => fn ($q) => $q->latestVersions()->latest(),
                'evidence',
                'notes.author:id,uuid,name',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function nextCaseNumber(): string
    {
        $year = now()->year;
        $count = LegalCase::withTrashed()->whereYear('created_at', $year)->count() + 1;

        return sprintf('CASE-%d-%05d', $year, $count);
    }
}
