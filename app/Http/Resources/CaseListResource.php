<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight projection for the cases data table (one row per case).
 *
 * @mixin LegalCase
 */
class CaseListResource extends JsonResource
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
            'type' => $this->case_type?->label(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label(), 'color' => $this->status->color()],
            'priority' => ['value' => $this->priority->value, 'label' => $this->priority->label(), 'color' => $this->priority->color()],
            'favorability' => $this->favorability,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->uuid,
                'name' => $this->client->name,
            ] : null),
            'lead_lawyer' => $this->whenLoaded('leadLawyer', fn () => $this->leadLawyer ? [
                'name' => $this->leadLawyer->name,
                'initials' => $this->leadLawyer->initials(),
            ] : null),
            'court_name' => $this->court_name,
            'next_hearing_at' => $this->next_hearing_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
