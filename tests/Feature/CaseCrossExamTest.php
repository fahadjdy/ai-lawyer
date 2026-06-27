<?php

declare(strict_types=1);

use App\Models\CaseAiInsight;
use App\Models\CaseEvent;
use App\Models\LegalCase;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function bootOwnerForCrossExam(): User
{
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    $user->assignRole('firm_owner');
    test()->actingAs($user);

    return $user;
}

/** Fake a Claude cross-exam response so tests never hit the network. */
function fakeCrossExamClaude(): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'opponent' => [
                        ['question' => 'Why did your client wait three weeks to file the FIR?', 'category' => 'Timeline/Delay', 'strategy' => 'Explain the client was gathering evidence.'],
                        ['question' => 'Can you produce the original agreement?', 'category' => 'Documentary', 'strategy' => 'Keep the original ready and exhibited.'],
                    ],
                    'judge' => [
                        ['question' => 'Which section grounds the cheating allegation?', 'category' => 'Legal basis', 'strategy' => 'Point to S.420 and the inducement on record.'],
                    ],
                    'disclaimer' => 'AI-anticipated questions for preparation only.',
                ]),
            ]],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);
}

it('blocks guests from the cross-exam endpoint', function () {
    $case = LegalCase::factory()->create(['team_id' => Team::factory()->create()->id]);

    $this->postJson("/cases/{$case->uuid}/cross-questions")->assertUnauthorized();
});

it('returns anticipated opponent and judge questions', function () {
    $user = bootOwnerForCrossExam();
    config(['services.anthropic.key' => 'test-key']);
    fakeCrossExamClaude();
    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'description' => 'The accused cheated the complainant of five lakh rupees and bounced a cheque.',
    ]);

    $this->postJson("/cases/{$case->uuid}/cross-questions")
        ->assertOk()
        ->assertJsonPath('result.opponent.0.question', 'Why did your client wait three weeks to file the FIR?')
        ->assertJsonPath('result.opponent.0.category', 'Timeline/Delay')
        ->assertJsonPath('result.opponent.1.question', 'Can you produce the original agreement?')
        ->assertJsonPath('result.judge.0.category', 'Legal basis')
        ->assertJsonCount(2, 'result.opponent')
        ->assertJsonCount(1, 'result.judge');
});

it('forbids users who cannot view cases', function () {
    test()->seed(RolePermissionSeeder::class);
    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]); // no cases.view permission
    test()->actingAs($user);
    config(['services.anthropic.key' => 'test-key']);
    $case = LegalCase::factory()->create(['team_id' => $team->id]);

    $this->postJson("/cases/{$case->uuid}/cross-questions")->assertForbidden();
});

it('reports a clean error when the API key is missing', function () {
    $user = bootOwnerForCrossExam();
    config(['services.anthropic.key' => '']);
    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'description' => 'The accused cheated the complainant and forged documents.',
    ]);

    $response = $this->postJson("/cases/{$case->uuid}/cross-questions")->assertStatus(422);

    expect($response->json('message'))->toContain('not configured');
});

it('caches the cross-exam result and reuses one row on regenerate', function () {
    $user = bootOwnerForCrossExam();
    config(['services.anthropic.key' => 'test-key']);
    fakeCrossExamClaude();
    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'description' => 'The accused cheated the complainant of five lakh rupees and bounced a cheque.',
    ]);

    $this->postJson("/cases/{$case->uuid}/cross-questions")->assertOk()->assertJsonPath('stale', false);
    expect(CaseAiInsight::where('case_id', $case->id)->where('kind', 'cross_exam')->count())->toBe(1);

    // Regenerating updates the same cached row rather than piling up duplicates.
    $this->postJson("/cases/{$case->uuid}/cross-questions")->assertOk();
    expect(CaseAiInsight::where('case_id', $case->id)->where('kind', 'cross_exam')->count())->toBe(1);
});

it('flags the stored cross-exam stale once the tracking timeline changes', function () {
    $user = bootOwnerForCrossExam();
    config(['services.anthropic.key' => 'test-key']);
    fakeCrossExamClaude();
    $case = LegalCase::factory()->create([
        'team_id' => $user->team_id,
        'created_by' => $user->id,
        'description' => 'The accused cheated the complainant and bounced a cheque.',
    ]);

    $this->postJson("/cases/{$case->uuid}/cross-questions")->assertOk();
    $insight = CaseAiInsight::where('case_id', $case->id)->where('kind', 'cross_exam')->firstOrFail();
    // Fresh: stored signature matches the current case.
    expect($insight->signature)->toBe(CaseAiInsight::signatureFor($case));

    // A new tracking entry moves the case on → the stored result is now stale.
    CaseEvent::create([
        'team_id' => $case->team_id,
        'case_id' => $case->id,
        'stage' => 'investigation',
        'title' => 'Charge sheet filed',
        'sections' => ['420', '467'],
    ]);

    expect($insight->signature)->not->toBe(CaseAiInsight::signatureFor($case->fresh()));
});
