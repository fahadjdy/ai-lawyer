<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CaseNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CaseNote
 */
class CaseNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'body' => $this->body,
            'is_pinned' => $this->is_pinned,
            'author' => new UserSummaryResource($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
