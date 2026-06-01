<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Boots a firm with an owner (all permissions) and authenticates as them.
 * Named distinctly to avoid clashing with the helper in CaseManagementTest.
 */
function bootFirmOwnerForSearch(): User
{
    test()->seed(RolePermissionSeeder::class);

    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');

    test()->actingAs($user);

    return $user;
}

it('redirects guests away from the search endpoint', function () {
    $this->getJson('/search?q=test')->assertUnauthorized();
});

it('ignores queries shorter than two characters', function () {
    bootFirmOwnerForSearch();

    $this->getJson('/search?q=a')
        ->assertOk()
        ->assertJson(['results' => []]);
});

it('finds a case by title and returns a deep link', function () {
    $user = bootFirmOwnerForSearch();

    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'title' => 'Zephyr Holdings v. Revenue Board',
    ]);

    $response = $this->getJson('/search?q=Zephyr')->assertOk();

    $response->assertJsonFragment([
        'group' => 'Cases',
        'type' => 'case',
        'title' => 'Zephyr Holdings v. Revenue Board',
        'url' => "/cases/{$case->uuid}",
    ]);
});

it('finds clients, hearings and tasks in one query', function () {
    $user = bootFirmOwnerForSearch();

    $client = Client::factory()->create([
        'team_id' => $user->team_id,
        'name' => 'Quasar Logistics Pvt Ltd',
    ]);

    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'client_id' => $client->id,
        'title' => 'Quasar Logistics Contract Dispute',
    ]);

    Hearing::factory()->create([
        'team_id' => $user->team_id,
        'case_id' => $case->id,
        'purpose' => 'Quasar interim arguments',
    ]);

    Task::factory()->create([
        'team_id' => $user->team_id,
        'case_id' => $case->id,
        'title' => 'Draft Quasar reply affidavit',
    ]);

    $body = $this->getJson('/search?q=Quasar')->assertOk()->json('results');
    $groups = collect($body)->pluck('group')->unique()->values()->all();

    expect($groups)->toContain('Cases')
        ->and($groups)->toContain('Clients')
        ->and($groups)->toContain('Hearings')
        ->and($groups)->toContain('Tasks');
});

it('never leaks records from another firm (multi-tenant isolation)', function () {
    bootFirmOwnerForSearch();

    $otherTeam = Team::factory()->create();
    LegalCase::factory()->create([
        'team_id' => $otherTeam->id,
        'title' => 'Phantom Secret Matter',
    ]);

    $body = $this->getJson('/search?q=Phantom')->assertOk()->json('results');

    expect($body)->toBeEmpty();
});

it('hides record types the user lacks permission to view', function () {
    // Seed so the permission catalogue exists, then grant exactly one ability:
    // the user can view cases but explicitly NOT clients.
    test()->seed(RolePermissionSeeder::class);

    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->givePermissionTo('cases.view');
    test()->actingAs($user);

    $client = Client::factory()->create([
        'team_id' => $team->id,
        'name' => 'Restricted Client Co',
    ]);
    LegalCase::factory()->create([
        'team_id' => $team->id,
        'created_by' => $user->id,
        'client_id' => $client->id,
        'title' => 'Restricted Client Matter',
    ]);

    $groups = collect($this->getJson('/search?q=Restricted')->assertOk()->json('results'))->pluck('group');

    expect($groups)->toContain('Cases')          // permitted → visible
        ->and($groups)->not->toContain('Clients'); // not permitted → hidden
});
