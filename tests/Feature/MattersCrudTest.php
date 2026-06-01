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
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/** Boot a firm owner (all permissions) and authenticate. */
function bootOwnerForMatters(): User
{
    test()->seed(RolePermissionSeeder::class);

    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');
    test()->actingAs($user);

    return $user;
}

/* -------------------------------------------------------------------------- */
/*  Tasks                                                                       */
/* -------------------------------------------------------------------------- */

it('guests cannot create tasks', function () {
    $this->post('/tasks', ['title' => 'X', 'status' => 'todo', 'priority' => 'medium'])
        ->assertRedirect('/login');
});

it('creates a task on the board', function () {
    $user = bootOwnerForMatters();

    $this->post('/tasks', [
        'title' => 'Draft reply affidavit',
        'status' => 'todo',
        'priority' => 'high',
    ])->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'title' => 'Draft reply affidavit',
        'team_id' => $user->team_id,
        'status' => 'todo',
        'created_by' => $user->id,
    ]);
});

it('requires a title when creating a task', function () {
    bootOwnerForMatters();

    $this->post('/tasks', ['title' => '', 'status' => 'todo', 'priority' => 'medium'])
        ->assertSessionHasErrors('title');
});

it('moving a task to done stamps completed_at', function () {
    $user = bootOwnerForMatters();
    $task = Task::factory()->create(['team_id' => $user->team_id, 'status' => 'todo', 'completed_at' => null]);

    $this->put("/tasks/{$task->uuid}", ['status' => 'done'])->assertRedirect();

    expect($task->fresh()->completed_at)->not->toBeNull();
});

it('deletes a task', function () {
    $user = bootOwnerForMatters();
    $task = Task::factory()->create(['team_id' => $user->team_id]);

    $this->delete("/tasks/{$task->uuid}")->assertRedirect();

    $this->assertSoftDeleted('tasks', ['id' => $task->id]);
});

it('cannot update another firm\'s task', function () {
    bootOwnerForMatters();

    $other = Team::factory()->create();
    $foreign = Task::factory()->create(['team_id' => $other->id]);

    $this->put("/tasks/{$foreign->uuid}", ['status' => 'done'])->assertNotFound();
});

it('reorders tasks within a column via drag-and-drop', function () {
    $user = bootOwnerForMatters();
    $a = Task::factory()->create(['team_id' => $user->team_id, 'status' => 'todo', 'position' => 0]);
    $b = Task::factory()->create(['team_id' => $user->team_id, 'status' => 'todo', 'position' => 1]);
    $c = Task::factory()->create(['team_id' => $user->team_id, 'status' => 'todo', 'position' => 2]);

    // Move C to the top: new order C, A, B.
    $this->post('/tasks/reorder', [
        'status' => 'todo',
        'ids' => [$c->uuid, $a->uuid, $b->uuid],
    ])->assertRedirect();

    expect($c->fresh()->position)->toBe(0)
        ->and($a->fresh()->position)->toBe(1)
        ->and($b->fresh()->position)->toBe(2);
});

it('reorder moving into the done column stamps completed_at', function () {
    $user = bootOwnerForMatters();
    $task = Task::factory()->create(['team_id' => $user->team_id, 'status' => 'todo', 'completed_at' => null]);

    $this->post('/tasks/reorder', ['status' => 'done', 'ids' => [$task->uuid]])->assertRedirect();

    $task->refresh();
    expect($task->status->value)->toBe('done')
        ->and($task->completed_at)->not->toBeNull();
});

it('renders the task detail page with its tracked history', function () {
    $user = bootOwnerForMatters();
    $task = Task::factory()->create(['team_id' => $user->team_id, 'status' => 'todo']);

    // Generate audit history: two status moves.
    $this->put("/tasks/{$task->uuid}", ['status' => 'in_progress']);
    $this->put("/tasks/{$task->uuid}", ['status' => 'done']);

    $this->get("/tasks/{$task->uuid}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tasks/Show')
            ->where('task.id', $task->uuid)
            ->where('task.status.value', 'done')
            ->has('timeline'));

    // At least the two status changes were recorded against this task.
    expect($task->activities()->where('event', 'updated')->count())->toBeGreaterThanOrEqual(2);
});

it('records who moved a task and to which state', function () {
    $user = bootOwnerForMatters();
    $task = Task::factory()->create(['team_id' => $user->team_id, 'status' => 'todo']);

    $this->put("/tasks/{$task->uuid}", ['status' => 'review']);

    $activity = $task->activities()->where('event', 'updated')->latest()->first();
    expect($activity->causer_id)->toBe($user->id)
        ->and(data_get($activity->attribute_changes, 'attributes.status'))->toBe('review')
        ->and(data_get($activity->attribute_changes, 'old.status'))->toBe('todo');
});

/* -------------------------------------------------------------------------- */
/*  Clients                                                                     */
/* -------------------------------------------------------------------------- */

it('creates a client', function () {
    $user = bootOwnerForMatters();

    $this->post('/clients', [
        'type' => 'individual',
        'name' => 'Rajesh Sharma',
        'email' => 'rajesh@example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('clients', [
        'name' => 'Rajesh Sharma',
        'team_id' => $user->team_id,
        'created_by' => $user->id,
    ]);
});

it('validates the client name', function () {
    bootOwnerForMatters();

    $this->post('/clients', ['type' => 'individual', 'name' => ''])
        ->assertSessionHasErrors('name');
});

it('updates and deletes a client', function () {
    $user = bootOwnerForMatters();
    $client = Client::factory()->create(['team_id' => $user->team_id, 'name' => 'Old Name']);

    $this->put("/clients/{$client->uuid}", ['type' => 'individual', 'name' => 'New Name'])
        ->assertRedirect();
    expect($client->fresh()->name)->toBe('New Name');

    $this->delete("/clients/{$client->uuid}")->assertRedirect('/clients');
    $this->assertSoftDeleted('clients', ['id' => $client->id]);
});

/* -------------------------------------------------------------------------- */
/*  Hearings                                                                    */
/* -------------------------------------------------------------------------- */

it('schedules a hearing against a case', function () {
    $user = bootOwnerForMatters();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    $this->post('/hearings', [
        'case_id' => $case->id,
        'scheduled_at' => '2026-08-01 10:30:00',
        'status' => 'scheduled',
        'purpose' => 'Final arguments',
    ])->assertRedirect();

    $this->assertDatabaseHas('hearings', [
        'case_id' => $case->id,
        'team_id' => $user->team_id,
        'purpose' => 'Final arguments',
    ]);
});

it('requires a case to schedule a hearing', function () {
    bootOwnerForMatters();

    $this->post('/hearings', ['scheduled_at' => '2026-08-01 10:30:00', 'status' => 'scheduled'])
        ->assertSessionHasErrors('case_id');
});

it('updates and deletes a hearing', function () {
    $user = bootOwnerForMatters();
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);
    $hearing = Hearing::factory()->create(['team_id' => $user->team_id, 'case_id' => $case->id, 'status' => 'scheduled']);

    $this->put("/hearings/{$hearing->uuid}", ['status' => 'completed', 'outcome' => 'Adjourned to next month'])
        ->assertRedirect();
    expect($hearing->fresh()->status->value)->toBe('completed');

    $this->delete("/hearings/{$hearing->uuid}")->assertRedirect();
    $this->assertSoftDeleted('hearings', ['id' => $hearing->id]);
});

/* -------------------------------------------------------------------------- */
/*  Permission gating                                                           */
/* -------------------------------------------------------------------------- */

it('forbids task creation without the manage permission', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->givePermissionTo('tasks.view'); // view only, no manage
    test()->actingAs($user);

    $this->post('/tasks', ['title' => 'Nope', 'status' => 'todo', 'priority' => 'low'])
        ->assertForbidden();
});
