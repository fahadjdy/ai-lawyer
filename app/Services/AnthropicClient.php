<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Anthropic Messages API (https://docs.claude.com).
 *
 * Used by the AI Case Assistant and the conversational Legal Chat Assistant.
 * Unlike an OpenAI/Groq chat-completions shim, this speaks the real Messages
 * API: the system prompt is a top-level field, tools use Anthropic's
 * {name, description, input_schema} shape, responses are content blocks, and
 * the stream is Anthropic's SSE event protocol. Claude is multimodal, so
 * message content may include image / PDF blocks (used to study case evidence).
 *
 * Note: on the Opus 4.x family, `temperature` / `top_p` / `top_k` are rejected
 * (400) — we deliberately never send them.
 */
class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    /**
     * One non-streaming completion. Returns the assistant text plus any tool_use
     * calls Claude wants made.
     *
     * @param  array<int, array<string, mixed>>  $messages  user/assistant turns (content: string | block[])
     * @param  array<int, array<string, mixed>>  $tools  Anthropic tool definitions
     * @return array{content: string, tool_calls: array<int, array{id: string, name: string, input: array<string, mixed>}>, stop_reason: string}
     */
    public function message(string $system, array $messages, int $maxTokens, array $tools = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->withOptions(['verify' => $this->caBundle()])
            ->timeout(120)
            ->post(self::ENDPOINT, $this->payload($system, $messages, $maxTokens, $tools, false));

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        return $this->parse((array) $response->json());
    }

    /**
     * One streaming completion. Forwards text deltas to {@see $onDelta} as they
     * arrive and accumulates any tool_use calls. Returns once the message ends
     * (or 'aborted' if the client disconnects mid-stream).
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{content: string, tool_calls: array<int, array{id: string, name: string, input: array<string, mixed>}>, finish: string}
     */
    public function stream(string $system, array $messages, int $maxTokens, callable $onDelta, array $tools = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->withOptions(['verify' => $this->caBundle(), 'stream' => true])
            ->timeout(180)
            ->post(self::ENDPOINT, $this->payload($system, $messages, $maxTokens, $tools, true));

        if ($response->status() >= 400) {
            throw new RuntimeException($this->errorMessage($response->json()));
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $content = '';
        $finish = 'end_turn';
        $toolBlocks = [];     // index => ['id'=>, 'name'=>, 'partial'=>'']
        $stop = false;

        while (! $body->eof() && ! $stop) {
            if (connection_aborted()) {
                return ['content' => $content, 'tool_calls' => $this->finishTools($toolBlocks), 'finish' => 'aborted'];
            }

            $buffer .= $body->read(2048);

            while (($nl = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $nl));
                $buffer = substr($buffer, $nl + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue; // Anthropic also sends `event:` lines — the JSON carries its own type.
                }

                $json = json_decode(trim(substr($line, 5)), true);
                if (! is_array($json)) {
                    continue;
                }

                switch ($json['type'] ?? '') {
                    case 'content_block_start':
                        $cb = $json['content_block'] ?? [];
                        if (($cb['type'] ?? '') === 'tool_use') {
                            $toolBlocks[$json['index'] ?? 0] = ['id' => (string) ($cb['id'] ?? ''), 'name' => (string) ($cb['name'] ?? ''), 'partial' => ''];
                        }
                        break;

                    case 'content_block_delta':
                        $delta = $json['delta'] ?? [];
                        if (($delta['type'] ?? '') === 'text_delta') {
                            $text = (string) ($delta['text'] ?? '');
                            if ($text !== '') {
                                $content .= $text;
                                $onDelta($text);
                            }
                        } elseif (($delta['type'] ?? '') === 'input_json_delta' && isset($toolBlocks[$json['index'] ?? 0])) {
                            $toolBlocks[$json['index']]['partial'] .= (string) ($delta['partial_json'] ?? '');
                        }
                        break;

                    case 'message_delta':
                        if (($sr = data_get($json, 'delta.stop_reason')) !== null) {
                            $finish = (string) $sr;
                        }
                        break;

                    case 'message_stop':
                        $stop = true;
                        break 2;
                }
            }
        }

        return ['content' => $content, 'tool_calls' => $this->finishTools($toolBlocks), 'finish' => $finish];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function payload(string $system, array $messages, int $maxTokens, array $tools, bool $stream): array
    {
        $payload = [
            'model' => (string) config('services.anthropic.model', 'claude-opus-4-8'),
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = ['type' => 'auto'];
        }

        if ($stream) {
            $payload['stream'] = true;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{content: string, tool_calls: array<int, array{id: string, name: string, input: array<string, mixed>}>, stop_reason: string}
     */
    private function parse(array $json): array
    {
        $content = '';
        $toolCalls = [];

        foreach ((array) ($json['content'] ?? []) as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $content .= (string) ($block['text'] ?? '');
            } elseif ($type === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? ''),
                    'name' => (string) ($block['name'] ?? ''),
                    'input' => (array) ($block['input'] ?? []),
                ];
            }
        }

        return ['content' => $content, 'tool_calls' => $toolCalls, 'stop_reason' => (string) ($json['stop_reason'] ?? '')];
    }

    /**
     * @param  array<int, array{id: string, name: string, partial: string}>  $blocks
     * @return array<int, array{id: string, name: string, input: array<string, mixed>}>
     */
    private function finishTools(array $blocks): array
    {
        ksort($blocks);
        $out = [];

        foreach ($blocks as $b) {
            if (($b['name'] ?? '') === '') {
                continue;
            }
            $input = json_decode($b['partial'] !== '' ? $b['partial'] : '{}', true);
            $out[] = [
                'id' => $b['id'] !== '' ? $b['id'] : 'tool_'.$b['name'],
                'name' => $b['name'],
                'input' => is_array($input) ? $input : [],
            ];
        }

        return array_values($out);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $key = (string) config('services.anthropic.key');

        if ($key === '') {
            throw new RuntimeException('AI assistant is not configured. Add ANTHROPIC_API_KEY to your .env file.');
        }

        return [
            'x-api-key' => $key,
            'anthropic-version' => self::VERSION,
        ];
    }

    /**
     * @param  mixed  $json
     */
    private function errorMessage($json): string
    {
        return (string) data_get($json, 'error.message', 'The AI request failed.');
    }

    /**
     * Resolve the TLS CA bundle: an explicit env path, else the bundle shipped in
     * storage/ (so HTTPS works on Windows/Laragon), else the system default.
     */
    private function caBundle(): string|bool
    {
        $configured = config('services.anthropic.ca_bundle');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $shipped = storage_path('app/cacert.pem');

        return is_file($shipped) ? $shipped : true;
    }
}
