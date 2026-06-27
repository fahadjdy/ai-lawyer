<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        // Audit trail is scoped to this firm's own members only.
        $teamUserIds = User::query()
            ->where('team_id', $request->user()->team_id)
            ->pluck('id');

        $activities = Activity::query()
            ->with('causer:id,uuid,name')
            // Scope the audit trail to actions performed by this firm's members.
            ->whereIn('causer_id', $teamUserIds)
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'log_name' => $a->log_name,
                'subject_type' => class_basename((string) $a->subject_type),
                'causer' => $a->causer?->name,
                'changes' => $a->properties,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('activity/Index', [
            'activities' => $activities,
            'filters' => $request->only(['event']),
        ]);
    }
}
