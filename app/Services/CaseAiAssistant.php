<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * AI Case Assistant. Asks Groq (OpenAI-compatible chat completions) to (a)
 * structure a case and suggest applicable IPC sections, and (b) suggest
 * sections for a single case update/title. Output is strict JSON.
 *
 * Informational only — the prompt and UI make clear this is not legal advice.
 */
class CaseAiAssistant
{
    /**
     * Full analysis: structured summary + suggested IPC sections, taking the
     * case's tracking history into account when present.
     *
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    public function analyze(array $case): array
    {
        $parsed = $this->chat($this->systemPrompt(), $this->userPrompt($case), 0.35, 1500);

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

        $parsed = $this->chat($this->sectionsSystemPrompt(), $context, 0.3, 700);

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
     * Single chat-completion call returning the decoded JSON object content.
     *
     * @return array<string, mixed>
     */
    private function chat(string $system, string $user, float $temperature, int $maxTokens): array
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
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException((string) data_get($response->json(), 'error.message', 'The AI request failed.'));
        }

        $parsed = json_decode((string) data_get($response->json(), 'choices.0.message.content', ''), true);

        if (! is_array($parsed)) {
            throw new RuntimeException('The AI returned an unexpected response. Please try again.');
        }

        return $parsed;
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
}
