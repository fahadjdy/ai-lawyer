<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function bootOwnerForRoles(): User
{
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');
    test()->actingAs($user);

    return $user;
}

it('shows the roles & rights page to an admin', function () {
    bootOwnerForRoles();

    $this->get('/roles')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('roles/Index')
            ->has('roles')
            ->has('groups'));
});

it('forbids non-admins from role management', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('associate'); // no settings.manage
    test()->actingAs($user);

    $this->get('/roles')->assertForbidden();
});

it('updates a role\'s permissions', function () {
    bootOwnerForRoles();
    $clerk = Role::where('name', 'clerk')->where('guard_name', 'web')->first();
    expect($clerk->permissions->pluck('name'))->not->toContain('cases.create');

    $this->put("/roles/{$clerk->id}", ['permissions' => ['cases.view', 'cases.create', 'clients.view']])
        ->assertRedirect();

    expect($clerk->fresh()->permissions->pluck('name'))
        ->toContain('cases.create')
        ->toContain('cases.view')
        ->toContain('clients.view');
});

it('locks the firm owner role from edits', function () {
    bootOwnerForRoles();
    $owner = Role::where('name', 'firm_owner')->where('guard_name', 'web')->first();

    $this->put("/roles/{$owner->id}", ['permissions' => ['cases.view']])->assertForbidden();
});

it('rejects unknown permissions', function () {
    bootOwnerForRoles();
    $clerk = Role::where('name', 'clerk')->first();

    $this->put("/roles/{$clerk->id}", ['permissions' => ['cases.view', 'not.a.real.permission']])
        ->assertSessionHasErrors('permissions.1');
});
