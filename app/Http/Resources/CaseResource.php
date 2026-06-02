<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\CaseAiInsight;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LegalCase
 */
class CaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'case_number' => $this->case_number,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->enumPayload($this->case_type),
            'status' => $this->enumPayload($this->status),
            'priority' => $this->enumPayload($this->priority),
            'favorability' => $this->favorability,
            'court' => [
                'name' => $this->court_name,
                'type' => $this->court_type,
                'jurisdiction' => $this->jurisdiction,
                'judge_name' => $this->judge_name,
            ],
            'opposing_party' => $this->opposing_party,
            'opposing_counsel' => $this->opposing_counsel,
            'filing_date' => $this->filing_date?->toDateString(),
            'next_hearing_at' => $this->next_hearing_at?->toIso8601String(),
            'tags' => $this->tags ?? [],
            'client' => new ClientSummaryResource($this->whenLoaded('client')),
            'lead_lawyer' => new UserSummaryResource($this->whenLoaded('leadLawyer')),
            'assignees' => UserSummaryResource::collection($this->whenLoaded('assignees')),
            // Derived from the already-eager-loaded relations (no extra queries),
            // powering the detail page's at-a-glance metric strip.
            'counts' => [
                'hearings' => $this->whenLoaded('hearings', fn () => $this->hearings->count()),
                'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->count()),
                'tasks_done' => $this->whenLoaded('tasks', fn () => $this->tasks->filter(fn ($t) => $t->status === TaskStatus::Done)->count()),
                'documents' => $this->whenLoaded('documents', fn () => $this->documents->count()),
                'evidence' => $this->whenLoaded('evidence', fn () => $this->evidence->count()),
                'events' => $this->whenLoaded('events', fn () => $this->events->count()),
                'notes' => $this->whenLoaded('notes', fn () => $this->notes->count()),
            ],
            'hearings' => HearingResource::collection($this->whenLoaded('hearings')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'notes' => CaseNoteResource::collection($this->whenLoaded('notes')),
            // Case Tracking timeline (newest first) + the latest applicable sections.
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($e) => [
                'id' => $e->uuid,
                'stage' => ['value' => $e->stage->value, 'label' => $e->stage->label(), 'color' => $e->stage->color()],
                'title' => $e->title,
                'description' => $e->description,
                'sections' => $e->sections ?? [],
                'occurred_on' => $e->occurred_on?->toDateString(),
                'created_by' => $e->creator?->name,
                'created_at' => $e->created_at?->toIso8601String(),
            ])),
            'current_sections' => $this->whenLoaded('events', fn () => $this->events->first(fn ($e) => ! empty($e->sections))?->sections ?? []),
            // Cached AI results, keyed by kind. is_stale is true once the case (notably its
            // tracking timeline) has changed since the result was generated.
            'ai_insights' => $this->whenLoaded('aiInsights', function (): array {
                $signature = CaseAiInsight::signatureFor($this->resource);

                return $this->aiInsights->mapWithKeys(fn (CaseAiInsight $ins): array => [
                    $ins->kind => [
                        'payload' => $ins->payload,
                        'is_stale' => $ins->signature !== $signature,
                        'generated_at' => $ins->updated_at?->toIso8601String(),
                        'generated_by' => $ins->generator?->name,
                    ],
                ])->all();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Normalised enum payload for the front-end: { value, label, color }.
     *
     * @return array<string, string>|null
     */
    private function enumPayload($enum): ?array
    {
        if ($enum === null) {
            return null;
        }

        return ['value' => $enum->value, 'label' => $enum->label(), 'color' => $enum->color()];
    }
}
