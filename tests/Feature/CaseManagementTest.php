<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Boots a firm with an owner user (all permissions) and authenticates as them.
 */
function actingAsFirmOwner(): User
{
    test()->seed(RolePermissionSeeder::class);

    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');

    test()->actingAs($user);

    return $user;
}

it('redirects guests away from the dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('renders the dashboard for an authenticated user', function () {
    actingAsFirmOwner();

    $this->get('/dashboard')->assertOk();
});

it('lists cases for the firm', function () {
    $user = actingAsFirmOwner();
    LegalCase::factory()->count(3)->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    $this->get('/cases')->assertOk();
});

it('creates a case through the controller', function () {
    $user = actingAsFirmOwner();
    $client = Client::factory()->create(['team_id' => $user->team_id]);

    $response = $this->post('/cases', [
        'title' => 'Test Matter v. State',
        'client_id' => $client->id,
        'case_type' => 'civil',
        'status' => 'open',
        'priority' => 'high',
        'lead_lawyer_id' => $user->id,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('cases', [
        'title' => 'Test Matter v. State',
        'team_id' => $user->team_id,
        'status' => 'open',
    ]);
});

it('enforces multi-tenant isolation on case access', function () {
    actingAsFirmOwner();

    // A case belonging to a different firm must 404 (global TeamScope).
    $otherTeam = Team::factory()->create();
    $foreignCase = LegalCase::factory()->create(['team_id' => $otherTeam->id]);

    $this->get("/cases/{$foreignCase->uuid}")->assertNotFound();
});

it('validates required fields when creating a case', function () {
    actingAsFirmOwner();

    $this->post('/cases', ['title' => ''])
        ->assertSessionHasErrors(['title', 'case_type', 'status', 'priority']);
});

it('archives (soft-deletes) a case', function () {
    $user = actingAsFirmOwner();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    $this->delete("/cases/{$case->uuid}")->assertRedirect();

    $this->assertSoftDeleted('cases', ['id' => $case->id]);
});

it('lists trashed cases', function () {
    $user = actingAsFirmOwner();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);
    $case->delete();

    $this->get('/cases?trashed=1')->assertOk();
});

it('restores a soft-deleted case', function () {
    $user = actingAsFirmOwner();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);
    $case->delete();

    $this->put("/cases/{$case->uuid}/restore")->assertRedirect();

    $this->assertNotSoftDeleted('cases', ['id' => $case->id]);
});

it('permanently deletes a trashed case', function () {
    $user = actingAsFirmOwner();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);
    $case->delete();

    $this->delete("/cases/{$case->uuid}/force")->assertRedirect();

    $this->assertDatabaseMissing('cases', ['id' => $case->id]);
});

it('bulk-archives selected cases', function () {
    $user = actingAsFirmOwner();
    $cases = LegalCase::factory()->count(2)->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    $this->post('/cases/bulk', [
        'action' => 'archive',
        'ids' => $cases->pluck('uuid')->all(),
    ])->assertRedirect();

    foreach ($cases as $case) {
        $this->assertSoftDeleted('cases', ['id' => $case->id]);
    }
});

it('bulk-restores trashed cases', function () {
    $user = actingAsFirmOwner();
    $cases = LegalCase::factory()->count(2)->create(['team_id' => $user->team_id, 'created_by' => $user->id]);
    $cases->each->delete();

    $this->post('/cases/bulk', [
        'action' => 'restore',
        'ids' => $cases->pluck('uuid')->all(),
    ])->assertRedirect();

    foreach ($cases as $case) {
        $this->assertNotSoftDeleted('cases', ['id' => $case->id]);
    }
});
