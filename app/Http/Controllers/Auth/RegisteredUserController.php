<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'firm_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // A new sign-up provisions a fresh firm (tenant) with this user as its
        // owner, so the account is never left team-less.
        $user = DB::transaction(function () use ($request): User {
            $team = Team::create([
                'name' => $request->string('firm_name')->value(),
                'slug' => Str::slug($request->string('firm_name')->value()).'-'.Str::lower(Str::random(6)),
                'email' => $request->string('email')->value(),
            ]);

            $user = User::create([
                'team_id' => $team->id,
                'name' => $request->string('name')->value(),
                'email' => $request->string('email')->value(),
                'password' => Hash::make($request->string('password')->value()),
                'is_active' => true,
            ]);

            $team->update(['owner_id' => $user->id]);

            // Provision this firm's own roles, then make the signup its owner.
            RolePermissions::ensurePermissions();
            RolePermissions::provision($team->id);
            app(PermissionRegistrar::class)->setPermissionsTeamId($team->id);
            $user->assignRole(RoleType::FirmOwner->value);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
