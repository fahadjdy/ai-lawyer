<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        $members = User::query()
            ->withCount(['ledCases', 'assignedTasks'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->uuid,
                'name' => $u->name,
                'email' => $u->email,
                'designation' => $u->designation,
                'initials' => $u->initials(),
                'is_active' => $u->is_active,
                'roles' => $u->getRoleNames(),
                'cases_count' => $u->led_cases_count,
                'tasks_count' => $u->assigned_tasks_count,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('team/Index', [
            'members' => $members,
        ]);
    }
}
