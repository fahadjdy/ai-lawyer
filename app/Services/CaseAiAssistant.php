<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * AI Case Assistant. Asks Claude (Anthropic Messages API) to (a) structure a
 * case and suggest applicable IPC sections, and (b) suggest sections for a
 * single case update/title. Output is strict JSON.
 *
 * Informational only — the prompt and UI make clear this is not legal advice.
 */
class CaseAiAssistant
{
    public function __construct(private readonly AnthropicClient $ai) {}

    /**
     * Full analysis: structured summary + suggested IPC sections, taking the
     * case's tracking history into account when present.
     *
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    public function analyze(array $case): array
    {
        $parsed = $this->chat($this->systemPrompt(), $this->userPrompt($case), 1500);

        return $this->normalize($parsed);
    }

    /**
     * Lightweight: suggest the most relevant IPC section numbers for a single
     * update/title, using the case as context. Powers the tracking form's
     * auto-fill.
     *
     * @param  array<string, mixed>  $case
     * @return array<int, array{section: string, title: string}>
     */
    public function suggestSections(array $case): array
    {
        $context = implode("\n", array_filter([
            'Case title: '.($case['title'] ?? ''),
            'Case type: '.($case['case_type'] ?? ''),
            ! empty($case['description']) ? 'Facts: '.$case['description'] : null,
            ! empty($case['focus']) ? 'Current update / title: '.$case['focus'] : null,
        ]));

        $parsed = $this->chat($this->sectionsSystemPrompt(), $context, 700);

        $out = [];
        foreach ((array) data_get($parsed, 'sections', []) as $s) {
            if (is_array($s) && trim((string) ($s['section'] ?? '')) !== '') {
                $out[] = ['section' => trim((string) $s['section']), 'title' => trim((string) ($s['title'] ?? ''))];
            } elseif (is_string($s) && trim($s) !== '') {
                $out[] = ['section' => trim($s), 'title' => ''];
            }
        }

        return $out;
    }

    /**
     * Anticipate the cross-examination: the questions the opposing counsel and
     * the judge/bench are each likely to put, paired with a short preparation
     * strategy. Case-aware — it considers the tracking history when present.
     * Powers the "Cross-examination prep" panel on the case detail page.
     *
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    public function crossExamQuestions(array $case): array
    {
        $parsed = $this->chat($this->crossExamSystemPrompt(), $this->crossExamUserPrompt($case), 1800);

        return $this->normalizeCrossExam($parsed);
    }

    /**
     * Single Claude call returning the decoded JSON object from the text content.
     *
     * @return array<string, mixed>
     */
    private function chat(string $system, string $user, int $maxTokens): array
    {
        $result = $this->ai->message($system, [['role' => 'user', 'content' => $user]], $maxTokens);

        $parsed = $this->decodeJson($result['content']);

        if (! is_array($parsed)) {
            throw new RuntimeException('The AI returned an unexpected response. Please try again.');
        }

        return $parsed;
    }

    /**
     * Decode the model's JSON output, tolerating any stray prose by extracting
     * the outermost {...} object if a direct decode fails.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $text): ?array
    {
        $text = trim($text);

        $parsed = json_decode($text, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are an experienced Indian criminal-law practitioner advising a lawyer who is
        drafting a complaint/FIR. From the case facts provided, do two things:

        1. Write a clear, professional case summary (3-5 sentences) and list the key facts.
        2. Identify the Indian Penal Code (IPC) sections that most likely apply — exactly
           the way a practising lawyer would. For each section give: the section number,
           its title, and a one-line reason tied directly to the facts. Where useful, name
           the equivalent Bharatiya Nyaya Sanhita (BNS), 2023 section inside the reason.

        Be DECISIVE and SPECIFIC, like ChatGPT would:
        - ALWAYS suggest the most relevant sections — normally 2 to 6. Never hedge with an
          empty list when the facts disclose any wrong (cheating, theft, hurt, threat,
          breach of trust, forgery, criminal intimidation, cruelty, negligence, etc.).
        - Only return an empty "ipc_sections" array if the description is blank or has no
          legally meaningful facts at all.
        - If a "Case tracking history" is provided, treat it as authoritative on how the
          matter evolved — suggest the sections that apply NOW (the latest position), and
          reflect sections added or dropped after investigation in your reasoning.
        - Reason only from the facts given — do not invent facts — but commit to an answer.

        Respond with STRICT, valid JSON only — no markdown, no prose outside JSON — using
        exactly this shape:
        {
          "summary": "string",
          "key_facts": ["string", ...],
          "ipc_sections": [
            { "section": "string e.g. 420", "title": "string", "reason": "string" }
          ],
          "suggested_priority": "low | medium | high | urgent",
          "disclaimer": "string"
        }

        The disclaimer must state these are AI-generated suggestions for a lawyer's review,
        not legal advice, and that the IPC has largely been superseded by the Bharatiya
        Nyaya Sanhita (BNS), 2023 for offences on/after 1 July 2024 — so verify the code.
        PROMPT;
    }

    private function sectionsSystemPrompt(): string
    {
        return <<<'PROMPT'
        You are an experienced Indian criminal lawyer. Given a case and a specific update
        or title, return the Indian Penal Code (IPC) section numbers that most likely
        apply to it. Be decisive and specific — normally 2 to 6 sections. Prefer the
        "Current update / title" focus where given, but use the case facts for context.

        Respond with STRICT, valid JSON only, exactly:
        { "sections": [ { "section": "420", "title": "Cheating" } ] }

        Return an empty array only if there is nothing legally meaningful to map.
        PROMPT;
    }

    private function crossExamSystemPrompt(): string
    {
        return <<<'PROMPT'
        You are a seasoned Indian trial advocate preparing a lawyer for the hearing of
        the case described. Anticipate the cross-examination — the pointed questions the
        matter is likely to face — from TWO angles:

        1. "opponent": questions the OPPOSING COUNSEL would put to your client/witnesses
           to attack credibility, expose contradictions, highlight delay, question motive,
           and probe gaps or weaknesses in the evidence.
        2. "judge": questions the JUDGE / BENCH is likely to ask to clarify the facts,
           test the legal basis and maintainability, and weigh the sections invoked.

        For EACH question also provide:
        - "category": one or two words (e.g. Credibility, Timeline/Delay, Documentary,
          Motive, Legal basis, Jurisdiction, Evidence, Procedure).
        - "strategy": a one or two line preparation note — how to answer it or what to
          keep ready — practical and specific to these facts.

        Be decisive and specific, grounded ONLY in the facts and tracking history given —
        do not invent facts. Give roughly 4 to 7 questions per side.

        Respond with STRICT, valid JSON only — no markdown, no prose outside JSON — using
        exactly this shape:
        {
          "opponent": [
            { "question": "string", "category": "string", "strategy": "string" }
          ],
          "judge": [
            { "question": "string", "category": "string", "strategy": "string" }
          ],
          "disclaimer": "string"
        }

        The disclaimer must state these are AI-anticipated questions for a lawyer's
        preparation and review only — not a prediction of the actual proceedings, and not
        legal advice.
        PROMPT;
    }

    /**
     * Facts + tracking history framed for cross-examination anticipation. Kept
     * separate from {@see userPrompt()} so its trailing instruction stays on
     * topic (questions, not section mapping).
     *
     * @param  array<string, mixed>  $case
     */
    private function crossExamUserPrompt(array $case): string
    {
        $lines = [
            'Title: '.($case['title'] ?? '—'),
            'Case type: '.($case['case_type'] ?? '—'),
            'Court: '.($case['court_name'] ?? '—'),
            'Opposing party: '.($case['opposing_party'] ?? '—'),
            '',
            'Facts / description:',
            (string) ($case['description'] ?? ''),
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

        $lines[] = '';
        $lines[] = 'Anticipate the cross-examination from both the opposing counsel and the judge.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function userPrompt(array $case): string
    {
        $lines = [
            'Title: '.($case['title'] ?? '—'),
            'Case type: '.($case['case_type'] ?? '—'),
            'Court: '.($case['court_name'] ?? '—'),
            'Opposing party: '.($case['opposing_party'] ?? '—'),
            '',
            'Facts / description:',
            (string) ($case['description'] ?? ''),
        ];

        // The case's tracking timeline (expected oldest-first) so the model can
        // see how the matter — and its sections — evolved through the stages.
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
            $lines[] = '';
            $lines[] = 'Considering this progression, suggest the sections applicable NOW.';
        }

        return implode("\n", $lines);
    }

    /**
     * Coerce the model output into a predictable, safe shape for the frontend.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function normalize(array $parsed): array
    {
        $sections = [];
        foreach ((array) data_get($parsed, 'ipc_sections', []) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $sections[] = [
                'section' => trim((string) ($s['section'] ?? '')),
                'title' => trim((string) ($s['title'] ?? '')),
                'reason' => trim((string) ($s['reason'] ?? '')),
            ];
        }

        $priority = strtolower((string) data_get($parsed, 'suggested_priority', ''));

        return [
            'summary' => trim((string) data_get($parsed, 'summary', '')),
            'key_facts' => array_values(array_filter(array_map(
                static fn ($f): string => trim((string) $f),
                (array) data_get($parsed, 'key_facts', []),
            ))),
            'ipc_sections' => array_values(array_filter($sections, static fn ($s) => $s['section'] !== '')),
            'suggested_priority' => in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : null,
            'disclaimer' => trim((string) data_get($parsed, 'disclaimer', 'AI-generated suggestions for a lawyer’s review — not legal advice.')),
        ];
    }

    /**
     * Coerce the cross-exam model output into a predictable, safe shape: two
     * lists of {question, category, strategy} plus a disclaimer.
     *
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function normalizeCrossExam(array $parsed): array
    {
        return [
            'opponent' => $this->cleanQuestions(data_get($parsed, 'opponent', [])),
            'judge' => $this->cleanQuestions(data_get($parsed, 'judge', [])),
            'disclaimer' => trim((string) data_get(
                $parsed,
                'disclaimer',
                'AI-anticipated questions for a lawyer’s preparation — not legal advice or a prediction of the proceedings.',
            )),
        ];
    }

    /**
     * Normalise one side's question list, dropping anything without a question
     * and tolerating bare-string items.
     *
     * @param  mixed  $items
     * @return array<int, array{question: string, category: string, strategy: string}>
     */
    private function cleanQuestions($items): array
    {
        $out = [];
        foreach ((array) $items as $q) {
            if (! is_array($q)) {
                $text = trim((string) $q);
                if ($text !== '') {
                    $out[] = ['question' => $text, 'category' => '', 'strategy' => ''];
                }

                continue;
            }

            $question = trim((string) ($q['question'] ?? ''));
            if ($question === '') {
                continue;
            }

            $out[] = [
                'question' => $question,
                'category' => trim((string) ($q['category'] ?? '')),
                'strategy' => trim((string) ($q['strategy'] ?? '')),
            ];
        }

        return $out;
    }
}
