<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A discussion comment posted against a {@see Task}.
 */
class TaskComment extends Model
{
    use BelongsToTeam, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'team_id',
        'task_id',
        'user_id',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'task_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
