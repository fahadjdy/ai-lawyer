<?php

declare(strict_types=1);

use App\Models\CaseAiInsight;
use App\Models\LegalCase;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function bootOwnerForAnalyze(): User
{
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');
    test()->actingAs($user);

    return $user;
}

/** Fake a Groq analyze response so tests never hit the network. */
function fakeAnalyzeGroq(): void
{
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Structured summary of the matter.',
                        'key_facts' => ['Fact one', 'Fact two'],
                        'ipc_sections' => [
                            ['section' => '420', 'title' => 'Cheating', 'reason' => 'Dishonest inducement.'],
                        ],
                        'suggested_priority' => 'high',
                        'disclaimer' => 'AI suggestions — not legal advice.',
                    ]),
                ],
            ]],
        ], 200),
    ]);
}

it('blocks guests from the case-bound analyze endpoint', function () {
    $case = LegalCase::factory()->create(['team_id' => Team::factory()->create()->id]);

    $this->postJson("/cases/{$case->uuid}/analyze")->assertUnauthorized();
});

it('analyzes a saved case and caches the analysis', function () {
    $user = bootOwnerForAnalyze();
    config(['services.groq.key' => 'test-key']);
    fakeAnalyzeGroq();
    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'description' => 'The accused cheated the complainant of five lakh rupees and bounced a cheque.',
    ]);

    $this->postJson("/cases/{$case->uuid}/analyze")
        ->assertOk()
        ->assertJsonPath('result.summary', 'Structured summary of the matter.')
        ->assertJsonPath('result.ipc_sections.0.section', '420')
        ->assertJsonPath('stale', false);

    expect(CaseAiInsight::where('case_id', $case->id)->where('kind', 'analysis')->count())->toBe(1);
});

it('requires a sufficient description before analyzing a saved case', function () {
    $user = bootOwnerForAnalyze();
    config(['services.groq.key' => 'test-key']);
    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'description' => 'too short',
    ]);

    $response = $this->postJson("/cases/{$case->uuid}/analyze")->assertStatus(422);

    expect($response->json('message'))->toContain('description');
});

it('forbids analyzing a case the user cannot view', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]); // no cases.view permission
    test()->actingAs($user);
    config(['services.groq.key' => 'test-key']);
    $case = LegalCase::factory()->create(['team_id' => $team->id]);

    $this->postJson("/cases/{$case->uuid}/analyze")->assertForbidden();
});
