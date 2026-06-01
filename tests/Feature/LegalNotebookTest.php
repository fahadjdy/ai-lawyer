<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('redirects guests from the legal notebook', function () {
    $this->get('/legal-notebook')->assertRedirect('/login');
});

it('shows the legal notebook with acts and sections to a firm member', function () {
    $this->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('paralegal'); // any member can browse the reference
    $this->actingAs($user);

    $this->get('/legal-notebook')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('legal/Notebook')
            ->has('acts')
            ->where('acts.0.short', 'BNS')
            ->has('acts.0.sections'));
});
