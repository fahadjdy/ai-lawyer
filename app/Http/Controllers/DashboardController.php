<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Http\Resources\HearingResource;
use App\Http\Resources\TaskResource;
use App\Models\Client;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $teamId = auth()->user()->team_id;

        // Cache the heavier aggregate stats briefly per team to cut DB load on
        // the most-visited screen. Invalidated implicitly by the short TTL.
        $stats = Cache::remember("dashboard.stats.{$teamId}", now()->addMinutes(2), function (): array {
            return [
                'total_cases' => LegalCase::count(),
                'active_cases' => LegalCase::active()->count(),
                'clients' => Client::count(),
                'open_tasks' => Task::where('status', '!=', 'done')->count(),
                'overdue_tasks' => Task::overdue()->count(),
                'cases_by_status' => LegalCase::query()
                    ->selectRaw('status, COUNT(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->all(),
            ];
        });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'statusLegend' => CaseStatus::options(),
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
                ->limit(5)
                ->get()
                ->map(fn (LegalCase $c) => [
                    'id' => $c->uuid,
                    'case_number' => $c->case_number,
                    'title' => $c->title,
                    'status' => ['label' => $c->status->label(), 'color' => $c->status->color()],
                    'client' => $c->client?->name,
                ]),
        ]);
    }
}
