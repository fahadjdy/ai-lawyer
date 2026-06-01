<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bootOwnerForTeam(): User
{
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');
    test()->actingAs($user);

    return $user;
}

it('guests cannot add members', function () {
    $this->post('/team', ['name' => 'X', 'email' => 'x@y.com', 'role' => 'associate', 'password' => 'password123'])
        ->assertRedirect('/login');
});

it('adds a member to the firm with a role', function () {
    $owner = bootOwnerForTeam();

    $this->post('/team', [
        'name' => 'Priya Mehta',
        'email' => 'priya.new@firm.test',
        'designation' => 'Senior Associate',
        'role' => 'partner',
        'password' => 'secret123',
    ])->assertRedirect();

    $member = User::where('email', 'priya.new@firm.test')->first();
    expect($member)->not->toBeNull()
        ->and($member->team_id)->toBe($owner->team_id)
        ->and($member->hasRole('partner'))->toBeTrue();
});

it('validates required member fields', function () {
    bootOwnerForTeam();

    $this->post('/team', ['name' => '', 'email' => 'not-an-email', 'role' => 'bogus', 'password' => 'short'])
        ->assertSessionHasErrors(['name', 'email', 'role', 'password']);
});

it('forbids members without team.manage from adding members', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('paralegal'); // no team.manage
    test()->actingAs($user);

    $this->post('/team', ['name' => 'Z', 'email' => 'z@firm.test', 'role' => 'clerk', 'password' => 'password123'])
        ->assertForbidden();
});

it('updates a member\'s role', function () {
    $owner = bootOwnerForTeam();
    $member = User::factory()->create(['team_id' => $owner->team_id]);
    $member->assignRole('clerk');

    $this->put("/team/{$member->uuid}", ['name' => $member->name, 'role' => 'associate', 'is_active' => true])
        ->assertRedirect();

    expect($member->fresh()->hasRole('associate'))->toBeTrue()
        ->and($member->fresh()->hasRole('clerk'))->toBeFalse();
});

it('removes a member but not yourself', function () {
    $owner = bootOwnerForTeam();
    $member = User::factory()->create(['team_id' => $owner->team_id]);

    $this->delete("/team/{$member->uuid}")->assertRedirect();
    $this->assertDatabaseMissing('users', ['id' => $member->id]);

    // Cannot delete self.
    $this->delete("/team/{$owner->uuid}")->assertForbidden();
});

it('cannot manage a member from another firm', function () {
    bootOwnerForTeam();
    $otherTeam = Team::factory()->create();
    $foreign = User::factory()->create(['team_id' => $otherTeam->id]);

    $this->put("/team/{$foreign->uuid}", ['name' => 'Hacked', 'role' => 'associate'])->assertNotFound();
});
