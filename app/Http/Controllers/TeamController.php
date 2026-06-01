<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleType;
use App\Http\Requests\Team\StoreTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $teamId = $request->user()->team_id;

        $members = User::query()
            ->where('team_id', $teamId)
            ->withCount(['ledCases', 'assignedTasks'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->uuid,
                'name' => $u->name,
                'email' => $u->email,
                'designation' => $u->designation,
                'phone' => $u->phone,
                'initials' => $u->initials(),
                'is_active' => $u->is_active,
                'roles' => $u->getRoleNames(),
                'role' => $u->getRoleNames()->first(),
                'cases_count' => $u->led_cases_count,
                'tasks_count' => $u->assigned_tasks_count,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('team/Index', [
            'members' => $members,
            'options' => ['roles' => RoleType::options()],
            'can' => ['manage' => $request->user()->can('team.manage')],
        ]);
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $member = User::create([
            'team_id' => $request->user()->team_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'designation' => $data['designation'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $member->assignRole($data['role']);

        return back()->with('success', "{$member->name} added to your firm.");
    }

    public function update(UpdateTeamMemberRequest $request, User $user): RedirectResponse
    {
        $this->ensureSameTeam($request, $user);

        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'designation' => $data['designation'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? $user->is_active,
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Member updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('team.manage'), 403);
        $this->ensureSameTeam($request, $user);

        // Guard against removing yourself.
        abort_if($user->id === $request->user()->id, 403, 'You cannot remove yourself.');

        $user->delete();

        return back()->with('success', 'Member removed.');
    }

    private function ensureSameTeam(Request $request, User $user): void
    {
        abort_unless($user->team_id !== null && $user->team_id === $request->user()->team_id, 404);
    }
}
