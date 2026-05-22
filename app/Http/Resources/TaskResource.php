<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status ? ['value' => $this->status->value, 'label' => $this->status->label(), 'color' => $this->status->color()] : null,
            'priority' => $this->priority ? ['value' => $this->priority->value, 'label' => $this->priority->label(), 'color' => $this->priority->color()] : null,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'position' => $this->position,
            'assignee' => new UserSummaryResource($this->whenLoaded('assignee')),
            'case' => new CaseSummaryResource($this->whenLoaded('case')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
