<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LegalCase
 */
class CaseSummaryResource extends JsonResource
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
            'status' => $this->status ? ['value' => $this->status->value, 'label' => $this->status->label(), 'color' => $this->status->color()] : null,
            'priority' => $this->priority ? ['value' => $this->priority->value, 'label' => $this->priority->label(), 'color' => $this->priority->color()] : null,
        ];
    }
}
