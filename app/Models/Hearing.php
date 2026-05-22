<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HearingStatus;
use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Hearing extends Model
{
    use BelongsToTeam, HasFactory, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'uuid',
        'team_id',
        'case_id',
        'scheduled_at',
        'status',
        'purpose',
        'court_room',
        'judge_name',
        'notes',
        'outcome',
        'next_hearing_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'next_hearing_at' => 'datetime',
            'status' => HearingStatus::class,
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now())
            ->where('status', HearingStatus::Scheduled->value)
            ->orderBy('scheduled_at');
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('scheduled_at', [$from, $to]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['scheduled_at', 'status', 'purpose', 'outcome'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('hearing');
    }
}
