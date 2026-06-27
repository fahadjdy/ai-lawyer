<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single checklist (subtask) line on a {@see Task}.
 */
class TaskItem extends Model
{
    use BelongsToTeam, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'team_id',
        'task_id',
        'title',
        'is_done',
        'position',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'task_id' => 'integer',
            'created_by' => 'integer',
            'position' => 'integer',
            'is_done' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
