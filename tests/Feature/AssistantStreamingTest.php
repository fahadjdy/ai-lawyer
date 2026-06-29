<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use App\Services\AnthropicClient;
use App\Services\KnowledgeRetriever;
use App\Services\LegalChatAssistant;
use App\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A scriptable stand-in for the Anthropic client. Each stream() call pops the
 * next scripted step, which may stream text via $onDelta, return tool calls, set
 * a finish reason, or throw — letting us drive the agentic loop deterministically
 * without touching the network.
 */
class FakeAnthropicClient extends AnthropicClient
{
    /** @var array<int, array<string, mixed>> */
    public array $steps = [];

    public int $calls = 0;

    /** @var array<int, bool> whether tools were offered on each call, by index */
    public array $toolsOffered = [];

    public function stream(string $system, array $messages, int $maxTokens, callable $onDelta, array $tools = []): array
    {
        $this->toolsOffered[$this->calls] = $tools !== [];
        $step = $this->steps[$this->calls] ?? ['finish' => 'end_turn'];
        $this->calls++;

        if (($step['throw'] ?? false) === true) {
            throw new RuntimeException('simulated upstream failure');
        }

        if (($step['emit'] ?? '') !== '') {
            $onDelta((string) $step['emit']);
        }

        return [
            'content' => $step['content'] ?? ($step['emit'] ?? ''),
            'tool_calls' => $step['tool_calls'] ?? [],
            'finish' => $step['finish'] ?? 'end_turn',
        ];
    }
}

/**
 * Boot an assistant wired to a scripted fake client and a no-op retriever.
 *
 * @param  array<int, array<string, mixed>>  $steps
 * @return array{0: LegalChatAssistant, 1: FakeAnthropicClient}
 */
function bootAssistant(array $steps): array
{
    TeamContext::flush();

    $team = Team::factory()->create();
    $user = User::factory()->create(['team_id' => $team->id]);
    test()->actingAs($user);

    $fake = new FakeAnthropicClient;
    $fake->steps = $steps;
    app()->instance(AnthropicClient::class, $fake);

    mock(KnowledgeRetriever::class)
        ->shouldReceive('retrieve')
        ->andReturn(['context' => '', 'citations' => []]);

    return [app(LegalChatAssistant::class), $fake];
}

/** Run one turn, collecting emitted SSE events. */
function runTurn(LegalChatAssistant $assistant, string $prompt, array &$events): array
{
    return $assistant->streamConversation(
        [['role' => 'user', 'content' => $prompt]],
        null,
        auth()->user(),
        function (string $event, array $data) use (&$events): void {
            $events[] = ['event' => $event, 'data' => $data];
        },
    );
}

it('streams a plain answer in a single round when no tools are needed', function () {
    [$assistant, $fake] = bootAssistant([
        ['emit' => 'Anticipatory bail is sought under BNSS s. 482.', 'finish' => 'end_turn'],
    ]);

    $events = [];
    $result = runTurn($assistant, 'explain anticipatory bail in detail please', $events);

    expect($result['content'])->toBe('Anticipatory bail is sought under BNSS s. 482.');
    expect($result['incomplete'])->toBeFalse();
    expect($fake->calls)->toBe(1);
});

it('runs a tool mid-stream then completes the answer (the dead-end fix)', function () {
    [$assistant, $fake] = bootAssistant([
        ['tool_calls' => [['id' => 't1', 'name' => 'list_upcoming_hearings', 'input' => ['days' => '7']]], 'finish' => 'tool_use'],
        ['emit' => 'You have no upcoming hearings this week.', 'finish' => 'end_turn'],
    ]);

    $events = [];
    $result = runTurn($assistant, 'what hearings are coming up this week?', $events);

    expect($result['content'])->toBe('You have no upcoming hearings this week.');
    expect($result['incomplete'])->toBeFalse();
    expect($fake->calls)->toBe(2);

    // A status line for the current action was surfaced for the progress UI.
    $statuses = array_values(array_filter($events, fn ($e) => $e['event'] === 'status'));
    expect($statuses)->not->toBeEmpty();
    $texts = array_map(fn ($e) => (string) ($e['data']['text'] ?? ''), $statuses);
    expect(collect($texts)->contains(fn ($t) => str_contains(strtolower($t), 'hearing')))->toBeTrue();
});

it('throws (so the controller surfaces an error) when a later round fails before any text', function () {
    [$assistant] = bootAssistant([
        ['tool_calls' => [['id' => 't1', 'name' => 'list_upcoming_hearings', 'input' => []]], 'finish' => 'tool_use'],
        ['throw' => true],
    ]);

    $events = [];
    expect(fn () => runTurn($assistant, 'what hearings are coming up?', $events))
        ->toThrow(RuntimeException::class);
});

it('keeps partial text and flags it incomplete when a later round fails after some text', function () {
    [$assistant] = bootAssistant([
        ['emit' => 'Let me check that case…', 'tool_calls' => [['id' => 't1', 'name' => 'list_upcoming_hearings', 'input' => []]], 'finish' => 'tool_use'],
        ['throw' => true],
    ]);

    $events = [];
    $result = runTurn($assistant, 'how will this case be solved?', $events);

    expect($result['content'])->toBe('Let me check that case…');
    expect($result['incomplete'])->toBeTrue();
});

it('still runs the tool when the round was cut off by max_tokens', function () {
    [$assistant, $fake] = bootAssistant([
        ['tool_calls' => [['id' => 't1', 'name' => 'list_upcoming_hearings', 'input' => []]], 'finish' => 'max_tokens'],
        ['emit' => 'Here is the calendar summary.', 'finish' => 'end_turn'],
    ]);

    $events = [];
    $result = runTurn($assistant, 'what hearings are coming up?', $events);

    expect($fake->calls)->toBe(2);
    expect($result['content'])->toBe('Here is the calendar summary.');
});

it('terminates the loop and forces a final tools-off round even if the model never stops calling tools', function () {
    $toolStep = ['tool_calls' => [['id' => 't', 'name' => 'list_upcoming_hearings', 'input' => []]], 'finish' => 'tool_use'];
    [$assistant, $fake] = bootAssistant(array_fill(0, 8, $toolStep));

    $events = [];
    runTurn($assistant, 'list every hearing over and over please', $events);

    // MAX_TOOL_ROUNDS = 4 tool rounds (calls 0–3, tools offered) + 1 forced final
    // round with tools withheld = 5 calls total, then it stops.
    expect($fake->calls)->toBe(5);
    expect($fake->toolsOffered[3])->toBeTrue();
    expect($fake->toolsOffered[4])->toBeFalse();
});
