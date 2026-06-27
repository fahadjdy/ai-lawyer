<?php

declare(strict_types=1);

use App\Models\LegalCase;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function bootOwnerForAi(): User
{
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');
    test()->actingAs($user);

    return $user;
}

/** Fake a Claude (Anthropic Messages API) response so tests never hit the network. */
function fakeClaude(): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Structured summary of the matter.',
                    'key_facts' => ['Fact one', 'Fact two'],
                    'ipc_sections' => [
                        ['section' => '420', 'title' => 'Cheating', 'reason' => 'Dishonest inducement.'],
                        ['section' => '506', 'title' => 'Criminal intimidation', 'reason' => 'Threats made.'],
                    ],
                    'suggested_priority' => 'high',
                    'disclaimer' => 'AI suggestions — not legal advice.',
                ]),
            ]],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);
}

it('blocks guests from the AI endpoint', function () {
    $this->postJson('/cases/ai/analyze', ['description' => str_repeat('detail ', 6)])
        ->assertUnauthorized();
});

it('requires a sufficiently detailed description', function () {
    bootOwnerForAi();

    $this->postJson('/cases/ai/analyze', ['description' => 'too short'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('description');
});

it('returns a structured summary and IPC suggestions', function () {
    bootOwnerForAi();
    config(['services.anthropic.key' => 'test-key']);
    fakeClaude();

    $this->postJson('/cases/ai/analyze', [
        'title' => 'Sharma vs State',
        'case_type' => 'criminal',
        'description' => 'The accused cheated the complainant of five lakh rupees and issued a bounced cheque.',
    ])
        ->assertOk()
        ->assertJsonPath('result.summary', 'Structured summary of the matter.')
        ->assertJsonPath('result.ipc_sections.0.section', '420')
        ->assertJsonPath('result.ipc_sections.1.section', '506')
        ->assertJsonPath('result.suggested_priority', 'high');
});

it('forbids users who cannot author cases', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->givePermissionTo('cases.view'); // view only — no create/update
    test()->actingAs($user);
    config(['services.anthropic.key' => 'test-key']);

    $this->postJson('/cases/ai/analyze', ['description' => str_repeat('detail ', 6)])
        ->assertForbidden();
});

it('reports a clean error when the API key is missing', function () {
    bootOwnerForAi();
    config(['services.anthropic.key' => '']);

    $response = $this->postJson('/cases/ai/analyze', ['description' => str_repeat('detail ', 6)])
        ->assertStatus(422);

    expect($response->json('message'))->toContain('not configured');
});

it('accepts case tracking history for context-aware analysis', function () {
    bootOwnerForAi();
    config(['services.anthropic.key' => 'test-key']);
    fakeClaude();

    $this->postJson('/cases/ai/analyze', [
        'description' => 'The accused cheated the complainant and forged documents.',
        'history' => [
            ['stage' => 'FIR', 'title' => 'Cheating reported', 'sections' => ['420'], 'notes' => 'Initial'],
            ['stage' => 'Charge Sheet', 'title' => 'Forgery found', 'sections' => ['420', '467'], 'notes' => 'After investigation'],
        ],
    ])->assertOk()->assertJsonPath('result.ipc_sections.0.section', '420');
});

it('suggests sections for a tracking update from its title', function () {
    $user = bootOwnerForAi();
    config(['services.anthropic.key' => 'test-key']);
    $case = LegalCase::factory()->create(['team_id' => $user->team_id, 'created_by' => $user->id]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'sections' => [
                        ['section' => '420', 'title' => 'Cheating'],
                        ['section' => '467', 'title' => 'Forgery'],
                    ],
                ]),
            ]],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    $this->postJson("/cases/{$case->uuid}/suggest-sections", ['text' => 'Charge sheet for cheating and forgery'])
        ->assertOk()
        ->assertJsonPath('sections.0.section', '420')
        ->assertJsonPath('sections.1.section', '467');
});

it('forbids section suggestions without case edit permission', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->givePermissionTo('cases.view'); // view only — cannot edit
    test()->actingAs($user);
    config(['services.anthropic.key' => 'test-key']);
    $case = LegalCase::factory()->create(['team_id' => $team->id]);

    $this->postJson("/cases/{$case->uuid}/suggest-sections", ['text' => 'x'])->assertForbidden();
});
