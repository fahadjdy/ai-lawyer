<?php

declare(strict_types=1);

use App\Models\CaseEvent;
use App\Models\LegalCase;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function bootOwnerForTracking(): User
{
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');
    test()->actingAs($user);

    return $user;
}

it('adds a tracking entry with sections to a case', function () {
    $user = bootOwnerForTracking();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    $this->post("/cases/{$case->uuid}/events", [
        'stage' => 'chargesheet',
        'title' => 'Charge sheet filed after investigation',
        'sections' => ['420', '467', '406'],
        'occurred_on' => '2026-05-20',
    ])->assertRedirect();

    $this->assertDatabaseHas('case_events', [
        'case_id' => $case->id,
        'team_id' => $user->team_id,
        'stage' => 'chargesheet',
        'created_by' => $user->id,
    ]);

    expect($case->events()->first()->sections)->toBe(['420', '467', '406']);
});

it('requires a stage and title', function () {
    $user = bootOwnerForTracking();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    $this->post("/cases/{$case->uuid}/events", ['sections' => ['420']])
        ->assertSessionHasErrors(['stage', 'title']);
});

it('shows the tracking timeline and current sections on the case page', function () {
    $user = bootOwnerForTracking();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    CaseEvent::factory()->create([
        'team_id' => $user->team_id, 'case_id' => $case->id,
        'stage' => 'complaint', 'sections' => ['420'], 'occurred_on' => '2026-01-10',
    ]);
    CaseEvent::factory()->create([
        'team_id' => $user->team_id, 'case_id' => $case->id,
        'stage' => 'chargesheet', 'sections' => ['420', '467'], 'occurred_on' => '2026-03-15',
    ]);

    $this->get("/cases/{$case->uuid}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('cases/Show')
            ->has('case.data.events', 2)
            // Newest-first: latest sections become the "current" ones.
            ->where('case.data.current_sections', ['420', '467']));
});

it('lets the owner edit and delete a tracking entry', function () {
    $user = bootOwnerForTracking();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);
    $event = CaseEvent::factory()->create(['team_id' => $user->team_id, 'case_id' => $case->id, 'title' => 'Old']);

    $this->put("/cases/{$case->uuid}/events/{$event->uuid}", [
        'stage' => 'trial', 'title' => 'Trial commenced', 'sections' => ['420'],
    ])->assertRedirect();
    expect($event->fresh()->title)->toBe('Trial commenced');

    $this->delete("/cases/{$case->uuid}/events/{$event->uuid}")->assertRedirect();
    $this->assertDatabaseMissing('case_events', ['id' => $event->id]);
});

it('blocks tracking writes without edit permission', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->givePermissionTo('cases.view'); // view only
    test()->actingAs($user);
    $case = LegalCase::factory()->create(['team_id' => $team->id]);

    $this->post("/cases/{$case->uuid}/events", ['stage' => 'complaint', 'title' => 'Nope'])
        ->assertForbidden();
});

it('cannot add tracking to another firm\'s case', function () {
    bootOwnerForTracking();
    $otherTeam = Team::factory()->create();
    $foreign = LegalCase::factory()->create(['team_id' => $otherTeam->id]);

    $this->post("/cases/{$foreign->uuid}/events", ['stage' => 'complaint', 'title' => 'X'])
        ->assertNotFound();
});
