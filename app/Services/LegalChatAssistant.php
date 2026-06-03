<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Conversational AI legal assistant. Holds a multi-turn chat with a firm member,
 * grounded in Indian law, via Groq (OpenAI-compatible chat completions). Unlike
 * {@see CaseAiAssistant} (which returns strict JSON), this returns free-form
 * prose so the reply reads like a chat. A conversation may be anchored to a
 * specific case, whose facts and tracking history are injected as context.
 *
 * It is agentic and grounded: it can call {@see ChatTools} to work with the
 * firm's live data (cases, hearings, clients, tasks, statutes) and is fed
 * retrieved reference material by {@see KnowledgeRetriever} (RAG). Replies are
 * streamed token-by-token via {@see streamConversation()}.
 *
 * Informational only — the system prompt and UI make clear this is not legal advice.
 */
class LegalChatAssistant
{
    /** Hard ceiling on tool-call rounds before a written answer is forced. */
    private const MAX_ROUNDS = 3;

    public function __construct(
        private readonly ChatTools $tools,
        private readonly KnowledgeRetriever $retriever,
    ) {}

    /**
     * Stream the assistant's next reply, resolving any tool calls and grounding
     * the answer in retrieved reference material along the way. The {@see $emit}
     * callback receives ('status'|'delta', array) events to forward to the client.
     *
     * @param  array<int, array{role: string, content: string}>  $history  Conversation so far, oldest-first.
     * @param  array<string, mixed>|null  $case  Optional attached-case context.
     * @return array{content: string, citations: array<int, array<string, mixed>>}
     */
    public function streamConversation(array $history, ?array $case, User $user, callable $emit): array
    {
        $latest = $this->latestUserMessage($history);

        // Retrieve grounding material for substantive questions (skip greetings).
        $citations = [];
        $ragContext = '';
        if (mb_strlen(trim($latest)) >= 12) {
            $emit('status', ['text' => 'Reviewing relevant law and case records']);
            $retrieved = $this->retriever->retrieve($latest, isset($case['id']) ? (int) $case['id'] : null);
            $ragContext = $retrieved['context'];
            $citations = $retrieved['citations'];
        }

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($case, $ragContext)]];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        // When the question is about the firm's own data, resolve any tool calls
        // in a NON-streamed phase first — llama is far more reliable at emitting
        // structured tool calls when not streaming (streamed, it sometimes types
        // the tool name as prose). The final answer is then streamed for real.
        if ($this->needsFirmData($latest)) {
            $this->resolveTools($messages, $user, $emit, $citations);
        }

        $full = '';
        $onDelta = function (string $d) use ($emit, &$full): void {
            $full .= $d;
            $emit('delta', ['text' => $d]);
        };

        // Stream the answer (tools, if any, are already resolved into $messages).
        $this->streamChat($messages, [], $onDelta);

        return ['content' => $full, 'citations' => $this->dedupeCitations($citations)];
    }

    /**
     * Non-streamed tool-resolution loop: let the model call tools against the
     * firm's data, execute them, and fold the results back into $messages so the
     * streamed answer can draw on them. Emits a status line per tool call.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $citations
     */
    private function resolveTools(array &$messages, User $user, callable $emit, array &$citations): void
    {
        for ($round = 0; $round < self::MAX_ROUNDS - 1; $round++) {
            try {
                $res = $this->toolCall($messages);
            } catch (RuntimeException) {
                // A tool-phase hiccup (e.g. strict arg validation) must never sink
                // the reply — fall through and let the streamed pass answer plainly.
                return;
            }

            if ($res['tool_calls'] === []) {
                return; // The model chose not to use a tool; the streamed pass will answer.
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $res['content'] !== '' ? $res['content'] : null,
                'tool_calls' => array_map(fn (array $c): array => [
                    'id' => $c['id'],
                    'type' => 'function',
                    'function' => ['name' => $c['name'], 'arguments' => $c['arguments'] ?: '{}'],
                ], $res['tool_calls']),
            ];

            foreach ($res['tool_calls'] as $call) {
                $args = json_decode($call['arguments'] ?: '{}', true);
                $args = is_array($args) ? $args : [];
                $emit('status', ['text' => $this->tools->statusLabel($call['name'], $args)]);
                $out = $this->tools->execute($call['name'], $args, $user);
                $citations = array_merge($citations, $out['citations'] ?? []);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'name' => $call['name'],
                    'content' => (string) json_encode($out['data']),
                ];
            }
        }
    }

    /**
     * Heuristic: does the latest message concern the firm's own records (cases,
     * hearings, clients, tasks) rather than a purely general legal question? Used
     * to decide whether to run the tool-resolution phase at all.
     */
    private function needsFirmData(string $message): bool
    {
        if (preg_match('/\b(hearing|hearings|schedule|scheduled|calendar|upcoming|cause\s?list|next\s+week|this\s+week|client|clients|task|tasks|reminder|remind|to-?do|deadline|my\s+case|my\s+matter|our\s+case)\b/i', $message)) {
            return true;
        }

        // "create/add a task", explicit tool requests, or a case-number-like token.
        if (preg_match('/\b(create|add|note down)\b.*\btask\b/i', $message)) {
            return true;
        }
        if (stripos($message, 'tool') !== false) {
            return true;
        }

        return (bool) preg_match('/\b[A-Z]{2,}[-\/ ]?\d{2,}\b/', $message);
    }

    /**
     * One NON-streamed tool-aware completion. Returns the message content and any
     * structured tool calls (assembled from the standard response shape).
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{content: string, tool_calls: array<int, array{id: string, name: string, arguments: string}>}
     */
    private function toolCall(array $messages): array
    {
        $key = (string) config('services.groq.key');
        if ($key === '') {
            throw new RuntimeException('AI assistant is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->withOptions(['verify' => $this->caBundle()])
            ->timeout(60)
            ->post(rtrim((string) config('services.groq.base_url'), '/').'/chat/completions', [
                'model' => config('services.groq.model'),
                'temperature' => 0.2,
                'max_tokens' => 1024,
                'tools' => $this->tools->specs(),
                'tool_choice' => 'auto',
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            throw new RuntimeException((string) data_get($response->json(), 'error.message', 'The AI request failed.'));
        }

        $message = (array) data_get($response->json(), 'choices.0.message', []);

        $toolCalls = [];
        foreach ((array) ($message['tool_calls'] ?? []) as $tc) {
            $name = (string) data_get($tc, 'function.name', '');
            if ($name === '') {
                continue;
            }
            $toolCalls[] = [
                'id' => (string) ($tc['id'] ?? 'call_'.$name),
                'name' => $name,
                'arguments' => (string) data_get($tc, 'function.arguments', '{}'),
            ];
        }

        return ['content' => (string) ($message['content'] ?? ''), 'tool_calls' => $toolCalls];
    }

    /**
     * Generate the assistant's next reply given the conversation so far.
     * Non-streaming, no tools — kept as a simple fallback / for tests.
     *
     * @param  array<int, array{role: string, content: string}>  $history  Prior turns, oldest-first.
     * @param  array<string, mixed>|null  $case  Optional attached-case context.
     */
    public function reply(array $history, ?array $case = null): string
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($case)]];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        return $this->complete($messages, 0.4, 1200);
    }

    /**
     * Single chat-completion call returning the assistant's text content.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function complete(array $messages, float $temperature, int $maxTokens): string
    {
        $key = (string) config('services.groq.key');

        if ($key === '') {
            throw new RuntimeException('AI assistant is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->withOptions(['verify' => $this->caBundle()])
            ->timeout(45)
            ->post(rtrim((string) config('services.groq.base_url'), '/').'/chat/completions', [
                'model' => config('services.groq.model'),
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            throw new RuntimeException((string) data_get($response->json(), 'error.message', 'The AI request failed.'));
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

        if ($content === '') {
            throw new RuntimeException('The AI returned an empty response. Please try again.');
        }

        return $content;
    }

    /**
     * One streamed chat-completion call. Forwards content deltas to {@see $onDelta}
     * as they arrive and accumulates any tool calls (assembled across chunks).
     * Stops early — returning finish 'aborted' — if the client disconnects.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{content: string, tool_calls: array<int, array{id: string, name: string, arguments: string}>, finish: string}
     */
    private function streamChat(array $messages, array $tools, callable $onDelta): array
    {
        $key = (string) config('services.groq.key');

        if ($key === '') {
            throw new RuntimeException('AI assistant is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $payload = [
            'model' => config('services.groq.model'),
            'temperature' => 0.4,
            'max_tokens' => 1600,
            'stream' => true,
            'messages' => $messages,
        ];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->withOptions(['verify' => $this->caBundle(), 'stream' => true])
            ->timeout(120)
            ->post(rtrim((string) config('services.groq.base_url'), '/').'/chat/completions', $payload);

        if ($response->status() >= 400) {
            throw new RuntimeException((string) data_get($response->json(), 'error.message', 'The AI request failed.'));
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $content = '';
        $toolCalls = [];
        $finish = 'stop';

        while (! $body->eof()) {
            if (connection_aborted()) {
                return ['content' => $content, 'tool_calls' => array_values($toolCalls), 'finish' => 'aborted'];
            }

            $buffer .= $body->read(2048);

            while (($nl = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $nl));
                $buffer = substr($buffer, $nl + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));
                if ($data === '[DONE]') {
                    break 2;
                }

                $json = json_decode($data, true);
                if (! is_array($json)) {
                    continue;
                }

                $choice = $json['choices'][0] ?? [];
                $delta = $choice['delta'] ?? [];

                if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
                    $content .= $delta['content'];
                    $onDelta($delta['content']);
                }

                foreach (($delta['tool_calls'] ?? []) as $tc) {
                    $i = $tc['index'] ?? 0;
                    $toolCalls[$i] ??= ['id' => '', 'name' => '', 'arguments' => ''];
                    if (! empty($tc['id'])) {
                        $toolCalls[$i]['id'] = $tc['id'];
                    }
                    if (isset($tc['function']['name'])) {
                        $toolCalls[$i]['name'] .= $tc['function']['name'];
                    }
                    if (isset($tc['function']['arguments'])) {
                        $toolCalls[$i]['arguments'] .= (string) $tc['function']['arguments'];
                    }
                }

                if (! empty($choice['finish_reason'])) {
                    $finish = $choice['finish_reason'];
                }
            }
        }

        ksort($toolCalls);

        return ['content' => $content, 'tool_calls' => array_values($toolCalls), 'finish' => $finish];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function latestUserMessage(array $history): string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === 'user') {
                return (string) ($history[$i]['content'] ?? '');
            }
        }

        return '';
    }

    /**
     * Collapse duplicate citations (same type + label + url).
     *
     * @param  array<int, array<string, mixed>>  $citations
     * @return array<int, array<string, mixed>>
     */
    private function dedupeCitations(array $citations): array
    {
        $seen = [];
        $out = [];
        foreach ($citations as $c) {
            $key = ($c['type'] ?? '').'|'.($c['label'] ?? '').'|'.($c['url'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $c;
        }

        return array_slice($out, 0, 12);
    }

    /**
     * Resolve the TLS CA bundle to verify against: an explicit env path, else
     * the bundle shipped in storage/, else fall back to the system default (true).
     */
    private function caBundle(): string|bool
    {
        $configured = config('services.groq.ca_bundle');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $shipped = storage_path('app/cacert.pem');

        return is_file($shipped) ? $shipped : true;
    }

    /**
     * The assistant's persona and ground rules, with the attached case and any
     * retrieved reference material (RAG) folded in as context.
     *
     * @param  array<string, mixed>|null  $case
     */
    private function systemPrompt(?array $case, string $ragContext = ''): string
    {
        $base = <<<'PROMPT'
        You are an AI legal assistant for an Indian law firm, helping a lawyer with their day-to-day
        legal work. You answer questions on Indian law and legal practice — substantive law (the
        Indian Penal Code / Bharatiya Nyaya Sanhita 2023, CrPC / Bharatiya Nagarik Suraksha Sanhita
        2023, the Indian Evidence Act / Bharatiya Sakshya Adhiniyam 2023, the Code of Civil Procedure,
        Contract Act, Constitution, and the major special statutes), procedure, drafting, strategy,
        limitation, jurisdiction, and case management.

        You are in a continuous, back-and-forth conversation. Before you reply, READ THE WHOLE
        chat so far — every earlier question and answer — and respond to the user's LATEST message
        in that context, the way a real person would. Never answer in isolation, and never repeat
        an earlier reply word-for-word.

        Reply in the same language and tone the user is using — English, Hindi, or Hinglish.

        First work out what the user actually MEANS. Judge the intent; do NOT pattern-match exact
        words or wait for a specific phrase:
        - Short acknowledgements or casual reactions — for example "ok", "k", "okay", "hmm", "got
          it", "ok got it", "thik hai", "theek", "acha", "sahi", "right", "great", "nice", "thanks",
          "thank you", "👍", "bye" — mean the user has understood or is simply reacting. Reply
          briefly and warmly (a line or two) like a colleague, then offer a relevant NEXT STEP or
          suggestion drawn from the conversation so far — e.g. "Glad that helps! Want me to also
          cover the punishment and bail position, or draft a sample complaint?" Do NOT repeat your
          previous answer, do NOT dump fresh legal analysis, and do NOT add the legal disclaimer.
        - A vague or unclear message: gently ask what they would like next and suggest one or two
          useful directions based on what you have already discussed — don't guess or re-answer.
        - A follow-up or clarification: answer just that, building on what was already said.
        - A new substantive legal question: give the full treatment described below.

        If the user hasn't asked anything new, don't invent a question to answer — respond to what
        they actually said and move the conversation forward with a helpful suggestion.

        When answering a substantive legal question:
        - Be clear, practical and decisive, the way a senior practitioner would brief a colleague.
        - Cite the specific sections, acts or leading principles that apply. When you name an IPC
          section, give its BNS, 2023 equivalent where you know it (offences on/after 1 July 2024 are
          governed by the new codes).
        - Use short paragraphs and markdown — headings, bold for key terms, and bullet/numbered lists
          where they aid clarity. Keep answers focused; don't pad.
        - If a question is outside Indian law or you are unsure, say so plainly rather than inventing
          authority. Never fabricate citations, section numbers or case names.
        - When you state a legal conclusion, add a brief reminder that this is general information for
          a lawyer's own judgement, not formal legal advice. (Skip this reminder for greetings and
          small talk — it only belongs on actual legal answers.)
        - End with a short, practical suggestion of where to go next — a related issue worth
          checking, a document you could draft, or a follow-up question they may want to ask — so
          the conversation keeps moving forward.

        Working with the firm's real data — you have TOOLS:
        - search_cases / get_case: when the user refers to one of the firm's matters (by name,
          number or party) without it being attached, look it up instead of guessing.
        - list_upcoming_hearings: for ANY question about the calendar, schedule or what is coming up.
        - find_clients: to look up a client's details.
        - search_legal_sections: only to confirm a SPECIFIC section that is not already in the
          retrieved reference material below (statutes are usually pre-retrieved for you).
        - create_task: ONLY when the user clearly asks to create / add / note down a task or reminder.

        Rules for tools:
        - If the user asks about the firm's hearings, cases, clients or tasks — or explicitly says
          "use your tools" — you MUST call the relevant tool. Do not answer such questions from memory.
        - Call the tool FIRST. Do NOT write any answer text before a tool call; call it, then write
          your answer once, after the results come back.
        - You may call several tools. Weave the results into a natural answer; never read raw JSON
          back to the user. If a tool returns an error or nothing, say so plainly.
        - Do NOT use tools for purely general legal questions that need no firm-specific data.
        PROMPT;

        $context = trim(implode("\n\n", array_filter([$this->caseContext($case), trim($ragContext)])));

        return $context === '' ? $base : $base."\n\n".$context;
    }

    /**
     * Render the attached case as a context block the model can reason from.
     *
     * @param  array<string, mixed>|null  $case
     */
    private function caseContext(?array $case): string
    {
        if ($case === null) {
            return '';
        }

        $lines = [
            'The lawyer has attached the following case from the firm\'s records. When the question',
            'relates to it, ground your answer in these specifics; otherwise treat it as background.',
            '',
            'Title: '.($case['title'] ?? '—'),
            'Case number: '.($case['case_number'] ?? '—'),
            'Case type: '.($case['case_type'] ?? '—'),
            'Court: '.($case['court_name'] ?? '—'),
            'Opposing party: '.($case['opposing_party'] ?? '—'),
            '',
            'Facts / description:',
            trim((string) ($case['description'] ?? '')) !== '' ? (string) $case['description'] : '(none provided)',
        ];

        $history = $case['history'] ?? [];
        if (is_array($history) && $history !== []) {
            $lines[] = '';
            $lines[] = 'Case tracking history (oldest first):';
            foreach ($history as $h) {
                if (! is_array($h)) {
                    continue;
                }
                $secs = implode(', ', (array) ($h['sections'] ?? []));
                $note = trim((string) ($h['notes'] ?? ''));
                $lines[] = '- ['.($h['stage'] ?? 'Update').'] '.($h['title'] ?? '')
                    .($secs !== '' ? ' | sections: '.$secs : '')
                    .($note !== '' ? ' | note: '.$note : '');
            }
        }

        return "ATTACHED CASE CONTEXT\n".implode("\n", $lines);
    }
}
