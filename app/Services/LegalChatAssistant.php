<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use RuntimeException;

/**
 * Conversational AI legal assistant. Holds a multi-turn chat with a firm member,
 * grounded in Indian law, via Claude (Anthropic Messages API). Unlike
 * {@see CaseAiAssistant} (which returns strict JSON), this returns free-form
 * prose so the reply reads like a chat. A conversation may be anchored to a
 * specific case, whose facts, tracking history AND evidence (documents/photos/
 * PDFs) are injected as context — Claude is multimodal, so it studies the
 * attached files directly.
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
        private readonly AnthropicClient $ai,
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

        $system = $this->systemPrompt($case, $ragContext);

        $messages = [];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        // Multimodal: only when the latest question is actually about the case's
        // files do we attach them (images & PDFs are costly to re-send each turn).
        if ($this->mentionsEvidence($latest)) {
            $this->attachEvidence($messages, is_array($case['attachments'] ?? null) ? $case['attachments'] : []);
        }

        // When the question is about the firm's own data, resolve any tool calls
        // in a NON-streamed phase first, then stream the final answer for real.
        if ($this->needsFirmData($latest)) {
            $this->resolveTools($system, $messages, $user, $emit, $citations);
        }

        $full = '';
        $onDelta = function (string $d) use ($emit, &$full): void {
            $full .= $d;
            $emit('delta', ['text' => $d]);
        };

        // Stream the answer (tools, if any, are already resolved into $messages).
        $this->ai->stream($system, $messages, 1600, $onDelta);

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
    private function resolveTools(string $system, array &$messages, User $user, callable $emit, array &$citations): void
    {
        for ($round = 0; $round < self::MAX_ROUNDS - 1; $round++) {
            try {
                $res = $this->ai->message($system, $messages, 1024, $this->tools->specs());
            } catch (RuntimeException) {
                // A tool-phase hiccup (e.g. strict arg validation) must never sink
                // the reply — fall through and let the streamed pass answer plainly.
                return;
            }

            if ($res['tool_calls'] === []) {
                return; // The model chose not to use a tool; the streamed pass will answer.
            }

            // Replay Claude's tool_use turn, then feed back each tool_result.
            $assistant = [];
            if (trim($res['content']) !== '') {
                $assistant[] = ['type' => 'text', 'text' => $res['content']];
            }
            foreach ($res['tool_calls'] as $c) {
                $assistant[] = ['type' => 'tool_use', 'id' => $c['id'], 'name' => $c['name'], 'input' => (object) ($c['input'] ?: [])];
            }
            $messages[] = ['role' => 'assistant', 'content' => $assistant];

            $results = [];
            foreach ($res['tool_calls'] as $call) {
                $args = is_array($call['input']) ? $call['input'] : [];
                $emit('status', ['text' => $this->tools->statusLabel($call['name'], $args)]);
                $out = $this->tools->execute($call['name'], $args, $user);
                $citations = array_merge($citations, $out['citations'] ?? []);
                $results[] = ['type' => 'tool_result', 'tool_use_id' => $call['id'], 'content' => (string) json_encode($out['data'])];
            }
            $messages[] = ['role' => 'user', 'content' => $results];
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
     * Does the latest message actually ask about the case's files / evidence?
     * Only then do we attach the case's documents & photos to the turn — they
     * are costly to re-send, so we skip them on unrelated questions.
     */
    private function mentionsEvidence(string $message): bool
    {
        return (bool) preg_match(
            '/\b(document|documents|evidence|exhibit|exhibits|photo|photos|image|images|picture|pictures|pic|pics|file|files|attachment|attachments|pdf|scan|scanned|screenshot|proof|dekh|dekho|dekhna|padh|padho|padhna|dastavej|dastavez|saboot|sabut|tasveer|tasvir)\b/i',
            $message,
        );
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
        $system = $this->systemPrompt($case);

        $messages = [];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $content = trim($this->ai->message($system, $messages, 1200)['content']);

        if ($content === '') {
            throw new RuntimeException('The AI returned an empty response. Please try again.');
        }

        return $content;
    }

    /**
     * Attach the case's evidence (images & PDFs) to the latest user turn so Claude
     * studies them directly. Each attachment is {kind, media_type, disk, path}.
     * Other file types (video, audio, docx…) can't be fed to the model; the
     * controller lists them by name in the case context instead.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array{kind: string, media_type: string, disk: string, path: string}>  $attachments
     */
    private function attachEvidence(array &$messages, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        // Find the latest plain-text user turn (the question) to attach files to.
        $target = null;
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user' && is_string($messages[$i]['content'] ?? null)) {
                $target = $i;
                break;
            }
        }
        if ($target === null) {
            return;
        }

        $blocks = [];
        $total = 0;
        foreach ($attachments as $att) {
            if (count($blocks) >= 12 || $total > 18_000_000) {
                break; // bound request size (Anthropic caps requests at ~32MB).
            }

            try {
                if (! \Illuminate\Support\Facades\Storage::disk($att['disk'])->exists($att['path'])) {
                    continue;
                }
                $bytes = (string) \Illuminate\Support\Facades\Storage::disk($att['disk'])->get($att['path']);
            } catch (\Throwable) {
                continue;
            }
            if ($bytes === '') {
                continue;
            }
            $total += strlen($bytes);
            $data = base64_encode($bytes);

            if ($att['kind'] === 'pdf') {
                $blocks[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $data]];
            } else {
                $blocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $att['media_type'], 'data' => $data]];
            }
        }

        if ($blocks === []) {
            return;
        }

        // Files first, then the question text (Anthropic's recommended order).
        $blocks[] = ['type' => 'text', 'text' => (string) $messages[$target]['content']];
        $messages[$target]['content'] = $blocks;
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

        Studying the case's evidence — you can SEE files:
        - When a case is attached and the question concerns its files, the case's documents and
          evidence are provided to you directly as images and PDFs. Study them carefully and
          ground your answer in what you actually see — text in scanned documents, the contents
          of photos, figures in a PDF. Refer to specific exhibits by name when you rely on them.
        - The case context lists the documents and evidence on record. Some files (e.g. office
          documents) can't be shown to you — they are named there; reason from their description
          and say plainly you could not open the file itself.
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

        // Inventory of the case's documents & evidence. Images and PDFs are
        // attached for you to view directly; other files are listed by name.
        $filesNote = trim((string) ($case['files_note'] ?? ''));
        if ($filesNote !== '') {
            $lines[] = '';
            $lines[] = $filesNote;
        }

        return "ATTACHED CASE CONTEXT\n".implode("\n", $lines);
    }
}
