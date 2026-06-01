<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'counts' => [
                'hearings' => $this->whenCounted('hearings'),
                'tasks' => $this->whenCounted('tasks'),
                'documents' => $this->whenCounted('documents'),
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
