<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Enums\ClientType;
use App\Enums\HearingStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use App\Support\TeamContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-only analytics engine for the firm's own data. Every query runs through
 * the team-scoped Eloquent models (the {@see \App\Models\Scopes\TeamScope}
 * global scope), so results are automatically isolated to the current tenant —
 * there is no raw SQL and no way to reach another firm's rows.
 *
 * It powers the AI assistant's analytics tools: flexible counts/breakdowns
 * ({@see aggregate()}), per-user workload ({@see userCaseload()}), side-by-side
 * case comparison ({@see compareCases()}) and a firm snapshot ({@see overview()}).
 */
class AnalyticsService
{
    /** Hard cap on rows returned in any breakdown, to keep prompts & charts sane. */
    private const MAX_GROUPS = 30;

    /**
     * Count records for an entity, optionally broken down by a dimension and
     * narrowed by filters. Returns a chart/table-ready shape:
     * ['entity', 'total', 'group_by', 'breakdown' => [{label, value}], 'filters'].
     *
     * @param  array<string, string>  $filters  status|type|priority|lawyer|period
     * @return array<string, mixed>
     */
    public function aggregate(string $entity, ?string $groupBy, array $filters): array
    {
        return match ($entity) {
            'cases' => $this->aggregateCases($groupBy, $filters),
            'tasks' => $this->aggregateTasks($groupBy, $filters),
            'hearings' => $this->aggregateHearings($groupBy, $filters),
            'clients' => $this->aggregateClients($groupBy, $filters),
            'users' => $this->aggregateUsers($groupBy),
            default => ['error' => "Unknown entity '{$entity}'. Use one of: cases, tasks, hearings, clients, users."],
        };
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function aggregateCases(?string $groupBy, array $filters): array
    {
        $base = LegalCase::query();
        $this->applyCaseFilters($base, $filters);

        $total = (clone $base)->count();

        $breakdown = match ($groupBy) {
            'status' => $this->groupByEnum($base, 'status', CaseStatus::class),
            'type' => $this->groupByEnum($base, 'case_type', CaseType::class),
            'priority' => $this->groupByEnum($base, 'priority', CasePriority::class),
            'lawyer', 'lead_lawyer' => $this->groupByUser($base, 'lead_lawyer_id', 'Unassigned'),
            'client' => $this->groupByClient($base),
            'court', 'court_name' => $this->groupByString($base, 'court_name', 'No court set'),
            'month' => $this->groupByMonth($base, 'created_at'),
            default => null,
        };

        return $this->result('cases', $total, $groupBy, $breakdown, $filters);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function aggregateTasks(?string $groupBy, array $filters): array
    {
        $base = Task::query();
        if (! empty($filters['status'])) {
            $base->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $base->where('priority', $filters['priority']);
        }
        $this->applyPeriod($base, 'created_at', $filters['period'] ?? null);

        $total = (clone $base)->count();

        $breakdown = match ($groupBy) {
            'status' => $this->groupByEnum($base, 'status', TaskStatus::class),
            'priority' => $this->groupByEnum($base, 'priority', TaskPriority::class),
            'assignee', 'user' => $this->groupByUser($base, 'assigned_to', 'Unassigned'),
            default => null,
        };

        return $this->result('tasks', $total, $groupBy, $breakdown, $filters);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function aggregateHearings(?string $groupBy, array $filters): array
    {
        $base = Hearing::query();
        if (! empty($filters['status'])) {
            $base->where('status', $filters['status']);
        }
        $this->applyPeriod($base, 'scheduled_at', $filters['period'] ?? null);

        $total = (clone $base)->count();

        $breakdown = match ($groupBy) {
            'status' => $this->groupByEnum($base, 'status', HearingStatus::class),
            'month' => $this->groupByMonth($base, 'scheduled_at'),
            'judge', 'judge_name' => $this->groupByString($base, 'judge_name', 'No judge set'),
            default => null,
        };

        return $this->result('hearings', $total, $groupBy, $breakdown, $filters);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function aggregateClients(?string $groupBy, array $filters): array
    {
        $base = Client::query();
        if (! empty($filters['type'])) {
            $base->where('type', $filters['type']);
        }
        $this->applyPeriod($base, 'created_at', $filters['period'] ?? null);

        $total = (clone $base)->count();

        $breakdown = match ($groupBy) {
            'type' => $this->groupByEnum($base, 'type', ClientType::class),
            'city' => $this->groupByString($base, 'city', 'No city set'),
            'state' => $this->groupByString($base, 'state', 'No state set'),
            default => null,
        };

        return $this->result('clients', $total, $groupBy, $breakdown, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateUsers(?string $groupBy): array
    {
        $base = $this->teamUsers();
        $total = (clone $base)->count();

        $breakdown = match ($groupBy) {
            'designation' => $this->groupByString($base, 'designation', 'No designation'),
            'status', 'active' => [
                ['label' => 'Active', 'value' => (clone $base)->where('is_active', true)->count()],
                ['label' => 'Inactive', 'value' => (clone $base)->where('is_active', false)->count()],
            ],
            default => null,
        };

        return $this->result('users', $total, $groupBy, $breakdown, []);
    }

    /**
     * Per-user workload across the firm: cases led, cases assigned, and open
     * tasks. Optionally narrowed to users whose name matches $query.
     *
     * @return array<string, mixed>
     */
    public function userCaseload(?string $query): array
    {
        $users = $this->teamUsers()
            ->when($query !== null && $query !== '', fn (Builder $q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(self::MAX_GROUPS)
            ->get(['id', 'name', 'designation']);

        if ($users->isEmpty()) {
            return ['error' => $query ? "No user found matching '{$query}'." : 'No users found.'];
        }

        $rows = $users->map(fn (User $u): array => [
            'user' => $u->name,
            'designation' => $u->designation,
            'cases_led' => $u->ledCases()->count(),
            'cases_assigned' => $u->assignedCases()->count(),
            'open_tasks' => $u->assignedTasks()->where('status', '!=', TaskStatus::Done->value)->count(),
        ])->all();

        return [
            'users' => $rows,
            'count' => count($rows),
            // Chart-ready: total caseload (led + assigned) per user.
            'caseload_breakdown' => array_values(array_filter(
                array_map(fn (array $r): array => [
                    'label' => $r['user'],
                    'value' => $r['cases_led'] + $r['cases_assigned'],
                ], $rows),
                fn (array $r): bool => $r['value'] > 0,
            )),
        ];
    }

    /**
     * Side-by-side comparison of named cases.
     *
     * @param  array<int, string>  $caseNumbers
     * @return array<string, mixed>
     */
    public function compareCases(array $caseNumbers): array
    {
        $numbers = array_values(array_filter(array_map(
            static fn ($n): string => trim((string) $n),
            $caseNumbers,
        )));

        if ($numbers === []) {
            return ['error' => 'Provide at least one case number to compare.'];
        }

        $cases = LegalCase::with('client:id,name', 'leadLawyer:id,name')
            ->withCount(['hearings', 'documents', 'evidence', 'tasks', 'notes'])
            ->whereIn('case_number', $numbers)
            ->get();

        $found = $cases->map(fn (LegalCase $c): array => [
            'case_number' => $c->case_number,
            'title' => $c->title,
            'status' => $c->status?->label(),
            'type' => $c->case_type?->label(),
            'priority' => $c->priority?->label(),
            'client' => $c->client?->name,
            'lead_lawyer' => $c->leadLawyer?->name,
            'court' => $c->court_name,
            'filing_date' => $c->filing_date?->toDateString(),
            'next_hearing_at' => $c->next_hearing_at?->toDayDateTimeString(),
            'hearings' => $c->hearings_count,
            'documents' => $c->documents_count,
            'evidence' => $c->evidence_count,
            'tasks' => $c->tasks_count,
            'notes' => $c->notes_count,
        ])->all();

        $missing = array_values(array_diff(
            array_map('mb_strtolower', $numbers),
            $cases->map(fn (LegalCase $c): string => mb_strtolower((string) $c->case_number))->all(),
        ));

        return ['cases' => $found, 'found' => count($found), 'not_found' => $missing];
    }

    /**
     * A high-level snapshot of the whole firm.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $activeStatuses = array_map(static fn (CaseStatus $s): string => $s->value, CaseStatus::active());

        return [
            'cases' => [
                'total' => LegalCase::query()->count(),
                'active' => LegalCase::query()->whereIn('status', $activeStatuses)->count(),
                'by_status' => $this->groupByEnum(LegalCase::query(), 'status', CaseStatus::class),
                'by_type' => $this->groupByEnum(LegalCase::query(), 'case_type', CaseType::class),
            ],
            'clients' => Client::query()->count(),
            'hearings' => [
                'upcoming_30_days' => Hearing::query()
                    ->whereBetween('scheduled_at', [Carbon::now(), Carbon::now()->addDays(30)])
                    ->where('status', HearingStatus::Scheduled->value)
                    ->count(),
            ],
            'tasks' => [
                'open' => Task::query()->where('status', '!=', TaskStatus::Done->value)->count(),
                'overdue' => Task::query()->overdue()->count(),
            ],
            'team_members' => $this->teamUsers()->count(),
        ];
    }

    /* -----------------------------------------------------------------
     |  Filters & grouping helpers
     | -----------------------------------------------------------------
     */

    /**
     * @param  array<string, string>  $filters
     */
    private function applyCaseFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('case_type', $filters['type']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        if (! empty($filters['lawyer'])) {
            $ids = $this->teamUsers()->where('name', 'like', '%'.$filters['lawyer'].'%')->pluck('id')->all();
            $query->whereIn('lead_lawyer_id', $ids ?: [-1]);
        }
        $this->applyPeriod($query, 'created_at', $filters['period'] ?? null);
    }

    /**
     * Constrain a query to a named date window. Unknown/'all' is a no-op.
     */
    private function applyPeriod(Builder $query, string $column, ?string $period): void
    {
        [$from, $to] = $this->periodRange($period);
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }
        if ($to !== null) {
            $query->where($column, '<=', $to);
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function periodRange(?string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_30_days' => [$now->copy()->subDays(30), $now->copy()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [null, null],
        };
    }

    /**
     * Count rows grouped by a backed-enum column, labelled via the enum.
     *
     * @param  class-string  $enum
     * @return array<int, array{label: string, value: int}>
     */
    private function groupByEnum(Builder $base, string $column, string $enum): array
    {
        // NB: run on the Eloquent builder (not ->getQuery()) so the TeamScope
        // global scope is applied via pluck()'s internal toBase() — bypassing it
        // would leak other firms' rows.
        $counts = (clone $base)
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column);

        $out = [];
        foreach ($counts as $value => $count) {
            $label = $value === null || $value === ''
                ? 'Unspecified'
                : ($enum::tryFrom((string) $value)?->label() ?? (string) $value);
            $out[] = ['label' => $label, 'value' => (int) $count];
        }

        return $this->sortDesc($out);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function groupByString(Builder $base, string $column, string $emptyLabel): array
    {
        // NB: run on the Eloquent builder (not ->getQuery()) so the TeamScope
        // global scope is applied via pluck()'s internal toBase() — bypassing it
        // would leak other firms' rows.
        $counts = (clone $base)
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column);

        $out = [];
        foreach ($counts as $value => $count) {
            $label = trim((string) $value) === '' ? $emptyLabel : (string) $value;
            $out[] = ['label' => $label, 'value' => (int) $count];
        }

        return $this->sortDesc($out);
    }

    /**
     * Count rows grouped by a user foreign key, labelled with the user's name.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function groupByUser(Builder $base, string $column, string $nullLabel): array
    {
        // NB: run on the Eloquent builder (not ->getQuery()) so the TeamScope
        // global scope is applied via pluck()'s internal toBase() — bypassing it
        // would leak other firms' rows.
        $counts = (clone $base)
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column);

        $names = User::whereIn('id', collect($counts->keys())->filter()->all())->pluck('name', 'id');

        $out = [];
        foreach ($counts as $id => $count) {
            $label = $id ? ($names[$id] ?? 'User #'.$id) : $nullLabel;
            $out[] = ['label' => $label, 'value' => (int) $count];
        }

        return $this->sortDesc($out);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function groupByClient(Builder $base): array
    {
        $counts = (clone $base) // Eloquent builder → TeamScope applied (see groupByEnum note).
            ->select('client_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('client_id')
            ->pluck('aggregate', 'client_id');

        $names = Client::whereIn('id', collect($counts->keys())->filter()->all())->pluck('name', 'id');

        $out = [];
        foreach ($counts as $id => $count) {
            $label = $id ? ($names[$id] ?? 'Client #'.$id) : 'No client';
            $out[] = ['label' => $label, 'value' => (int) $count];
        }

        return $this->sortDesc($out);
    }

    /**
     * Count rows by calendar month of a date column. Done in PHP so it works
     * identically on SQLite (local) and MariaDB (prod) without date functions.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function groupByMonth(Builder $base, string $column): array
    {
        $dates = (clone $base)->whereNotNull($column)->pluck($column);

        $buckets = [];
        foreach ($dates as $date) {
            $carbon = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse((string) $date);
            $key = $carbon->format('Y-m');
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        ksort($buckets);

        $out = [];
        foreach ($buckets as $key => $value) {
            $out[] = ['label' => Carbon::createFromFormat('Y-m', $key)->format('M Y'), 'value' => $value];
        }

        // Chronological (not desc) — months read left-to-right on a trend chart.
        return array_slice($out, -self::MAX_GROUPS);
    }

    /**
     * @param  array<int, array{label: string, value: int}>  $rows
     * @return array<int, array{label: string, value: int}>
     */
    private function sortDesc(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return array_slice($rows, 0, self::MAX_GROUPS);
    }

    /**
     * Team-scoped user query. The User model is the auth model and intentionally
     * carries no global team scope, so we constrain it here explicitly.
     */
    private function teamUsers(): Builder
    {
        return User::query()->where('team_id', TeamContext::id());
    }

    /**
     * @param  array<int, array{label: string, value: int}>|null  $breakdown
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function result(string $entity, int $total, ?string $groupBy, ?array $breakdown, array $filters): array
    {
        $payload = [
            'entity' => $entity,
            'total' => $total,
        ];

        if ($groupBy !== null && $groupBy !== '') {
            $payload['group_by'] = $groupBy;
            $payload['breakdown'] = $breakdown ?? [];
            if ($breakdown === null) {
                $payload['note'] = "'{$groupBy}' is not a supported breakdown for {$entity}; returning the total only.";
            }
        }

        if ($filters !== []) {
            $payload['filters'] = $filters;
        }

        return $payload;
    }
}
