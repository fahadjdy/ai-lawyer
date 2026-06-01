<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Hearing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hearing
 */
class HearingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            // Raw FK — used only to pre-fill the edit form's case <select>.
            'case_id' => $this->case_id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'status' => $this->status ? ['value' => $this->status->value, 'label' => $this->status->label(), 'color' => $this->status->color()] : null,
            'purpose' => $this->purpose,
            'court_room' => $this->court_room,
            'judge_name' => $this->judge_name,
            'notes' => $this->notes,
            'outcome' => $this->outcome,
            'next_hearing_at' => $this->next_hearing_at?->toIso8601String(),
            'case' => new CaseSummaryResource($this->whenLoaded('case')),
        ];
    }
}
