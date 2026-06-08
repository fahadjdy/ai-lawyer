<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LegalCase;
use App\Models\LegalSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lightweight retrieval-augmented grounding for the chat assistant. From the
 * user's question it pulls the most relevant statute sections (the shared legal
 * library) and the firm's own related cases, returning both a compact context
 * block to inject into the prompt and a list of citations to surface in the UI.
 *
 * Keyword based (no embeddings) — fast, dependency-free, and good enough to keep
 * answers anchored to real sections and matters the firm has on file.
 */
class KnowledgeRetriever
{
    private const STOP_WORDS = [
        'the', 'and', 'for', 'what', 'which', 'with', 'that', 'this', 'from', 'your', 'have',
        'are', 'was', 'were', 'will', 'would', 'should', 'could', 'how', 'why', 'when', 'where',
        'who', 'whom', 'can', 'does', 'did', 'about', 'into', 'under', 'over', 'section', 'sections',
        'please', 'tell', 'give', 'explain', 'help', 'need', 'want', 'case', 'cases', 'law', 'legal',
        'india', 'indian', 'between', 'against', 'there', 'their', 'them', 'they', 'some', 'any',
    ];

    /**
     * @return array{context: string, citations: array<int, array<string, mixed>>}
     */
    public function retrieve(string $query, ?int $excludeCaseId = null): array
    {
        $keywords = $this->keywords($query);
        $numbers = $this->sectionNumbers($query);

        if ($keywords === [] && $numbers === []) {
            return ['context' => '', 'citations' => []];
        }

        $sections = $this->sections($keywords, $numbers);
        $cases = $this->cases($keywords, $excludeCaseId);

        return [
            'context' => $this->buildContext($sections, $cases),
            'citations' => $this->buildCitations($sections, $cases),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function keywords(string $query): array
    {
        $words = preg_split('/[^a-z0-9]+/i', mb_strtolower($query)) ?: [];

        $kept = array_filter(
            $words,
            fn (string $w): bool => mb_strlen($w) >= 4 && ! in_array($w, self::STOP_WORDS, true) && ! ctype_digit($w),
        );

        return array_slice(array_values(array_unique($kept)), 0, 6);
    }

    /**
     * @return array<int, string>
     */
    private function sectionNumbers(string $query): array
    {
        // Require 3–4 digits so incidental small numbers ("next 30 days") don't
        // pull in unrelated sections; most IPC/BNS section numbers are 3 digits.
        preg_match_all('/\b\d{3,4}[a-z]?\b/i', $query, $m);

        return array_slice(array_values(array_unique($m[0] ?? [])), 0, 4);
    }

    /**
     * Statute sections are a shared (non-team) library, so no scoping concern.
     *
     * @param  array<int, string>  $keywords
     * @param  array<int, string>  $numbers
     * @return Collection<int, LegalSection>
     */
    private function sections(array $keywords, array $numbers)
    {
        return LegalSection::query()
            ->where(function (Builder $q) use ($keywords, $numbers): void {
                foreach ($keywords as $kw) {
                    $q->orWhere('title', 'like', "%{$kw}%")
                        ->orWhere('description', 'like', "%{$kw}%");
                }
                foreach ($numbers as $n) {
                    $q->orWhere('section_number', $n)
                        ->orWhere('section_number', 'like', "{$n}%");
                }
            })
            ->limit(5)
            ->get();
    }

    /**
     * The firm's related cases — automatically team-scoped by the global scope.
     *
     * @param  array<int, string>  $keywords
     * @return Collection<int, LegalCase>
     */
    private function cases(array $keywords, ?int $excludeCaseId)
    {
        if ($keywords === []) {
            return collect();
        }

        return LegalCase::query()
            ->where(function (Builder $q) use ($keywords): void {
                foreach ($keywords as $kw) {
                    $q->orWhere('title', 'like', "%{$kw}%")
                        ->orWhere('opposing_party', 'like', "%{$kw}%");
                }
            })
            ->when($excludeCaseId !== null, fn (Builder $q) => $q->where('id', '!=', $excludeCaseId))
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }

    /**
     * @param  Collection<int, LegalSection>  $sections
     * @param  Collection<int, LegalCase>  $cases
     */
    private function buildContext($sections, $cases): string
    {
        if ($sections->isEmpty() && $cases->isEmpty()) {
            return '';
        }

        $lines = [
            'RETRIEVED REFERENCE MATERIAL (from the firm\'s legal library and case records).',
            'Use what is genuinely relevant and cite it; ignore anything that does not fit the question.',
        ];

        if ($sections->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Statute sections:';
            foreach ($sections as $s) {
                $summary = trim((string) $s->description);
                $lines[] = '- ['.$s->act_name.' §'.$s->section_number.'] '.$s->title
                    .($summary !== '' ? ' — '.mb_substr($summary, 0, 220) : '');
            }
        }

        if ($cases->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Related cases on file:';
            foreach ($cases as $c) {
                $lines[] = '- ['.($c->case_number ?: 'Case').'] '.$c->title
                    .($c->status ? ' — '.$c->status->label() : '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, LegalSection>  $sections
     * @param  Collection<int, LegalCase>  $cases
     * @return array<int, array<string, mixed>>
     */
    private function buildCitations($sections, $cases): array
    {
        $citations = [];

        foreach ($sections as $s) {
            $citations[] = [
                'type' => 'section',
                'label' => '§'.$s->section_number,
                'title' => $s->act_name.' — '.$s->title,
                'url' => '/legal-notebook',
            ];
        }

        foreach ($cases as $c) {
            $citations[] = [
                'type' => 'case',
                'label' => $c->case_number ?: 'Case',
                'title' => $c->title,
                'url' => '/cases/'.$c->uuid,
            ];
        }

        return $citations;
    }
}
