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
    /** How many tool-using rounds to allow before tools are withdrawn to force a written answer. */
    private const MAX_TOOL_ROUNDS = 4;

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

        // Stream the reply with tools available DURING streaming, looping through
        // any tool calls until a final written answer is produced.
        $agentic = $this->runAgentic($system, $messages, $user, $emit, $citations);

        return [
            'content' => $agentic['content'],
            'citations' => $this->dedupeCitations($citations),
            // True when a backend error interrupted us after some text had streamed,
            // so the controller can mark the saved reply as unfinished.
            'incomplete' => $agentic['incomplete'],
        ];
    }

    /**
     * The streaming agentic loop. Streams the model's reply token-by-token with the
     * full toolbox available; if the model decides to use a tool, the text so far is
     * already on screen, a status line is emitted for the CURRENT action, the tool
     * runs, its result is fed back, and we stream again — repeating until the model
     * writes a final answer (or the round budget is spent, at which point tools are
     * withdrawn to force one).
     *
     * Crucially, tools are available WHILE streaming (not in a separate pre-pass),
     * so a reply that begins "let me check that case…" actually follows through with
     * the lookup and the answer, instead of dead-ending with no response.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $citations
     * @return array{content: string, incomplete: bool}
     */
    private function runAgentic(string $system, array &$messages, User $user, callable $emit, array &$citations): array
    {
        $full = '';
        $onDelta = function (string $d) use ($emit, &$full): void {
            $full .= $d;
            $emit('delta', ['text' => $d]);
        };

        $tools = $this->tools->specs();

        for ($round = 0; ; $round++) {
            $offerTools = $round < self::MAX_TOOL_ROUNDS;

            try {
                $result = $this->ai->stream($system, $messages, 1600, $onDelta, $offerTools ? $tools : []);
            } catch (RuntimeException $e) {
                // Nothing usable streamed yet → let the controller surface the error
                // (and clear the dangling prompt). If we already streamed part of an
                // answer, keep it but flag it as interrupted, rather than silently
                // passing an unfinished lead-in off as a complete reply.
                if ($full === '') {
                    throw $e;
                }

                return ['content' => $full, 'incomplete' => true];
            }

            $finish = $result['finish'] ?? '';

            // Run tools when the model asked for them — including when it was cut off
            // by max_tokens mid-turn but still emitted a tool call (treat 'max_tokens'
            // like 'tool_use' here so the call isn't silently dropped). On the final
            // (no-tools) round, or a clean/aborted finish, we stop with what we have.
            $wantsTools = $offerTools
                && $result['tool_calls'] !== []
                && in_array($finish, ['tool_use', 'max_tokens'], true);

            if (! $wantsTools) {
                return ['content' => $full, 'incomplete' => false];
            }

            // Replay the model's tool_use turn, then feed back each tool result.
            $assistant = [];
            if (trim($result['content']) !== '') {
                $assistant[] = ['type' => 'text', 'text' => $result['content']];
            }
            foreach ($result['tool_calls'] as $c) {
                $assistant[] = ['type' => 'tool_use', 'id' => $c['id'], 'name' => $c['name'], 'input' => (object) ($c['input'] ?: [])];
            }
            $messages[] = ['role' => 'assistant', 'content' => $assistant];

            $results = [];
            foreach ($result['tool_calls'] as $call) {
                $args = is_array($call['input']) ? $call['input'] : [];
                $emit('status', ['text' => $this->tools->statusLabel($call['name'], $args)]);
                $out = $this->tools->execute($call['name'], $args, $user);
                $citations = array_merge($citations, $out['citations'] ?? []);
                $results[] = ['type' => 'tool_result', 'tool_use_id' => $call['id'], 'content' => (string) json_encode($out['data'])];
            }
            $messages[] = ['role' => 'user', 'content' => $results];

            // Heavy image/PDF attachments only need to be seen once. Drop them from
            // the history after the first round so they aren't re-uploaded (and
            // re-billed as fresh input tokens) on every tool-continuation round.
            if ($round === 0) {
                $this->stripHeavyAttachments($messages);
            }

            // Keep the progress indicator meaningful until the next text delta lands.
            $emit('status', ['text' => 'Reviewing the results and writing your answer']);
        }
    }

    /**
     * Replace any image / PDF blocks in the message history with a short text
     * placeholder, so large base64 attachments are sent to the model only once
     * (on the first round) instead of being re-uploaded on every tool round.
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function stripHeavyAttachments(array &$messages): void
    {
        foreach ($messages as &$message) {
            if (! is_array($message['content'] ?? null)) {
                continue;
            }
            foreach ($message['content'] as &$block) {
                if (in_array($block['type'] ?? '', ['image', 'document'], true)) {
                    $block = ['type' => 'text', 'text' => '[An evidence file was shown earlier in this conversation.]'];
                }
            }
            unset($block);
        }
        unset($message);
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

        Firm ANALYTICS — you can answer questions about the whole system with numbers:
        - get_statistics: count cases / tasks / hearings / clients / users, optionally grouped by a
          dimension (status, type, priority, lawyer, client, court, month, assignee…) and filtered
          (status, type, priority, lawyer, period). Use it for ANY "how many / total / kitne /
          breakdown / distribution / per lawyer / by status / this month" question.
        - get_user_caseload: how many cases a given user (or every user) leads and is assigned, plus
          their open tasks — for "how many cases does X have" or "caseload per lawyer".
        - compare_cases: put two or more named cases side by side — for "compare / combine CR-1 and CR-2".
        - firm_overview: a high-level snapshot of the whole firm — for "give me an overview of the system".
        - These analytics tools are ADMIN-ONLY. If one replies that the user lacks permission, tell
          them plainly that firm-wide analytics are restricted to admins — do not invent numbers.

        Rules for tools:
        - If the user asks about the firm's hearings, cases, clients, tasks, or any counts/breakdowns/
          comparisons of the firm's data — or explicitly says "use your tools" — you MUST call the
          relevant tool. Do not answer such questions from memory or guess numbers.
        - Call the tool FIRST. Do NOT write any answer text before a tool call; call it, then write
          your answer once, after the results come back.
        - You may call several tools. Weave the results into a natural answer; never read raw JSON
          back to the user. If a tool returns an error or nothing, say so plainly.
        - Do NOT use tools for purely general legal questions that need no firm-specific data.

        PRESENTING DATA — after an analytics/data tool returns, pick the CLEAREST format for the answer:
        - A single figure (e.g. a total): state it in one short, bold sentence — e.g. "You have **142**
          cases in total."
        - A breakdown or comparison across several rows: use a markdown TABLE.
        - A distribution or proportion, or counts across categories / months: add a CHART. Emit it as a
          fenced code block tagged `chart` containing JSON ONLY — nothing else inside the fence:

          ```chart
          {"type":"pie","title":"Cases by status","data":[{"label":"Open","value":12},{"label":"Closed","value":7}]}
          ```

          - "type": use "pie" or "donut" for parts of a whole (e.g. status/type mix); "bar" for
            comparing categories (e.g. cases per lawyer) or a month-by-month trend.
          - Use the EXACT labels and numbers from the tool result — never invent or round them away.
            Keep to at most 12 slices/bars (the tools already cap this).
          - You may include BOTH a one-line takeaway (and/or a small table) AND a chart. Put a brief
            sentence before the chart so the answer still reads well without it.
          - Reply in the user's language, but keep the JSON inside the ```chart block in English/ASCII.
        - Always ground every figure in tool output. These are management numbers — accuracy matters more
          than flourish, and the "not legal advice" reminder is not needed for pure data answers.

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
