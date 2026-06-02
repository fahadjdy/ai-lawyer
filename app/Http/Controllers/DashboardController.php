<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Http\Resources\HearingResource;
use App\Http\Resources\TaskResource;
use App\Models\Client;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->team_id;

        // The analytical aggregates are the expensive part of this most-visited
        // screen, so cache them briefly per team. Per-user / time-sensitive
        // lists (my tasks, activity feed) are fetched fresh below.
        $analytics = Cache::remember(
            "dashboard.analytics.{$teamId}",
            now()->addMinutes(2),
            fn (): array => $this->analytics($teamId),
        );

        return Inertia::render('Dashboard', array_merge($analytics, [
            'userName' => explode(' ', (string) auth()->user()->name)[0],
            'recentActivity' => $this->recentActivity($teamId),
            'upcomingHearings' => HearingResource::collection(
                Hearing::with('case:id,uuid,case_number,title,status')->upcoming()->limit(6)->get(),
            ),
            'myTasks' => TaskResource::collection(
                Task::with(['case:id,uuid,case_number,title', 'assignee'])
                    ->where('assigned_to', auth()->id())
                    ->where('status', '!=', 'done')
                    ->orderByRaw('due_at IS NULL, due_at ASC')
                    ->limit(6)
                    ->get(),
            ),
            'recentCases' => LegalCase::with('client:id,uuid,name')
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (LegalCase $c): array => [
                    'id' => $c->uuid,
                    'case_number' => $c->case_number,
                    'title' => $c->title,
                    'status' => ['label' => $c->status->label(), 'color' => $c->status->color()],
                    'priority' => ['label' => $c->priority->label(), 'color' => $c->priority->color()],
                    'client' => $c->client?->name,
                    'updated_at' => $c->updated_at?->toIso8601String(),
                ]),
        ]));
    }

    /**
     * Heavy, team-wide aggregates powering the KPI cards and charts.
     *
     * @return array<string, mixed>
     */
    private function analytics(?int $teamId): array
    {
        $now = now();

        return [
            'kpis' => $this->kpis(),
            'caseTrend' => $this->caseTrend(),
            'casesByStatus' => array_map(static fn (CaseStatus $s): array => [
                'label' => $s->label(),
                'value' => (int) (LegalCase::query()->where('status', $s->value)->count()),
                'color' => $s->color(),
            ], CaseStatus::cases()),
            'casesByType' => $this->casesByType(),
            'casesByPriority' => $this->casesByPriority(),
            'taskStats' => $this->taskStats(),
            'winRate' => $this->winRate(),
            'teamWorkload' => $this->teamWorkload($teamId),
            'generatedAt' => $now->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array{value:int, delta:?int, sub:string}>
     */
    private function kpis(): array
    {
        $thisMonth = now()->startOfMonth();

        $totalCases = LegalCase::count();
        $totalClients = Client::count();
        $newCases = LegalCase::where('created_at', '>=', $thisMonth)->count();
        $newClients = Client::where('created_at', '>=', $thisMonth)->count();

        // Month-over-month growth of a cumulative total: how much this month's
        // additions grew the prior base. Always >= 0 (or null when there's no
        // base yet), so the trend reads as momentum rather than a scary drop.
        $growth = static function (int $total, int $added): ?int {
            $base = $total - $added;

            return $base > 0 ? (int) round($added / $base * 100) : ($added > 0 ? 100 : null);
        };

        return [
            'total_cases' => [
                'value' => $totalCases,
                'delta' => $growth($totalCases, $newCases),
                'sub' => "+{$newCases} new this month",
            ],
            'active_cases' => [
                'value' => LegalCase::active()->count(),
                'delta' => null,
                'sub' => 'Currently in progress',
            ],
            'clients' => [
                'value' => $totalClients,
                'delta' => $growth($totalClients, $newClients),
                'sub' => "+{$newClients} onboarded this month",
            ],
            'open_tasks' => [
                'value' => Task::where('status', '!=', 'done')->count(),
                'delta' => null,
                'sub' => 'Across all matters',
            ],
            'hearings_week' => [
                'value' => Hearing::whereBetween('scheduled_at', [now(), now()->addDays(7)])->count(),
                'delta' => null,
                'sub' => 'Scheduled next 7 days',
            ],
            'overdue_tasks' => [
                'value' => Task::overdue()->count(),
                'delta' => null,
                'sub' => 'Past their due date',
            ],
        ];
    }

    /**
     * New cases vs hearings per month for the last 6 months. Bucketed in PHP so
     * the query stays portable across the SQLite/MariaDB drivers.
     *
     * @return array<int, array{label:string, cases:int, hearings:int}>
     */
    private function caseTrend(): array
    {
        $months = [];
        $cursor = now()->startOfMonth()->subMonths(5);
        for ($i = 0; $i < 6; $i++) {
            $months[$cursor->format('Y-m')] = ['label' => $cursor->format('M'), 'cases' => 0, 'hearings' => 0];
            $cursor->addMonth();
        }
        $rangeStart = now()->startOfMonth()->subMonths(5);

        LegalCase::where('created_at', '>=', $rangeStart)->pluck('created_at')
            ->each(function ($date) use (&$months): void {
                $key = $date->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['cases']++;
                }
            });

        Hearing::where('scheduled_at', '>=', $rangeStart)->pluck('scheduled_at')
            ->each(function ($date) use (&$months): void {
                $key = $date->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['hearings']++;
                }
            });

        return array_values($months);
    }

    /**
     * @return array<int, array{label:string, value:int}>
     */
    private function casesByType(): array
    {
        $counts = LegalCase::selectRaw('case_type, COUNT(*) as total')->groupBy('case_type')->pluck('total', 'case_type');

        return collect(CaseType::cases())
            ->map(static fn (CaseType $t): array => ['label' => $t->label(), 'value' => (int) ($counts[$t->value] ?? 0)])
            ->filter(static fn (array $r): bool => $r['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string, value:int, color:string}>
     */
    private function casesByPriority(): array
    {
        $counts = LegalCase::selectRaw('priority, COUNT(*) as total')->groupBy('priority')->pluck('total', 'priority');

        return array_map(static fn (CasePriority $p): array => [
            'label' => $p->label(),
            'value' => (int) ($counts[$p->value] ?? 0),
            'color' => $p->color(),
        ], CasePriority::cases());
    }

    /**
     * @return array{total:int, done:int, completion:int}
     */
    private function taskStats(): array
    {
        $byStatus = Task::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (int) $byStatus->sum();
        $done = (int) ($byStatus['done'] ?? 0);

        return [
            'total' => $total,
            'done' => $done,
            'completion' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    /**
     * Favourable-outcome rate across resolved cases (won + settled).
     *
     * @return array{value:int, resolved:int, favorable:int}
     */
    private function winRate(): array
    {
        $counts = LegalCase::selectRaw('status, COUNT(*) as total')
            ->whereIn('status', [CaseStatus::Won->value, CaseStatus::Lost->value, CaseStatus::Settled->value, CaseStatus::Closed->value])
            ->groupBy('status')
            ->pluck('total', 'status');

        $resolved = (int) $counts->sum();
        $favorable = (int) ($counts[CaseStatus::Won->value] ?? 0) + (int) ($counts[CaseStatus::Settled->value] ?? 0);

        return [
            'value' => $resolved > 0 ? (int) round($favorable / $resolved * 100) : 0,
            'resolved' => $resolved,
            'favorable' => $favorable,
        ];
    }

    /**
     * Per-lawyer load: open assigned tasks + active led cases, busiest first.
     *
     * @return array<int, array{name:string, initials:string, tasks:int, cases:int, load:int}>
     */
    private function teamWorkload(?int $teamId): array
    {
        $members = User::where('team_id', $teamId)->where('is_active', true)->get(['id', 'name']);
        $openTasks = Task::where('status', '!=', 'done')->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $activeCases = LegalCase::active()->selectRaw('lead_lawyer_id, COUNT(*) as total')->groupBy('lead_lawyer_id')->pluck('total', 'lead_lawyer_id');

        return $members
            ->map(static function (User $u) use ($openTasks, $activeCases): array {
                $tasks = (int) ($openTasks[$u->id] ?? 0);
                $cases = (int) ($activeCases[$u->id] ?? 0);

                return ['name' => $u->name, 'initials' => $u->initials(), 'tasks' => $tasks, 'cases' => $cases, 'load' => $tasks + $cases];
            })
            ->sortByDesc('load')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * Latest audit-trail entries for this firm's members.
     *
     * @return array<int, array{id:int, event:?string, subject:string, causer:?string, when:?string}>
     */
    private function recentActivity(?int $teamId): array
    {
        $teamUserIds = User::where('team_id', $teamId)->pluck('id');

        return Activity::with('causer:id,name')
            ->whereIn('causer_id', $teamUserIds)
            ->latest()
            ->limit(7)
            ->get()
            ->map(static fn (Activity $a): array => [
                'id' => $a->id,
                'event' => $a->event,
                'subject' => class_basename((string) $a->subject_type),
                'causer' => $a->causer?->name,
                'when' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
