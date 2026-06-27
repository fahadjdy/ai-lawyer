<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Task extends Model
{
    use BelongsToTeam, HasFactory, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'uuid',
        'team_id',
        'case_id',
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'completed_at',
        'position',
        'assigned_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'case_id' => 'integer',
            'assigned_to' => 'integer',
            'created_by' => 'integer',
            'position' => 'integer',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Checklist (subtask) lines, ordered for display.
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Discussion thread, oldest first.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    /**
     * The audit trail recorded against this task by spatie/activitylog. The
     * LogsActivity trait records entries but doesn't expose the inverse
     * relation, so we declare it for the task detail timeline.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->where('status', '!=', TaskStatus::Done->value);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && $this->status !== TaskStatus::Done;
    }

    /**
     * Audit trail: log the meaningful fields so the task detail page can show
     * who moved it to which state, reassigned it, or changed its schedule.
     * `position` is intentionally excluded so drag-reordering doesn't spam it.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'priority', 'assigned_to', 'case_id', 'due_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('task');
    }
}
