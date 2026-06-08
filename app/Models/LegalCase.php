<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The central aggregate of the platform. A case ties together the client,
 * assigned lawyers, hearings, documents, evidence, tasks and notes.
 *
 * Named `LegalCase` because `Case` is a reserved word in PHP; the underlying
 * table remains `cases`.
 */
class LegalCase extends Model
{
    use BelongsToTeam, HasFactory, HasUuid, LogsActivity, Searchable, SoftDeletes, Sortable;

    protected $table = 'cases';

    /**
     * @return array<int, string>
     */
    public function sortableColumns(): array
    {
        return ['title', 'case_number', 'status', 'priority', 'filing_date', 'next_hearing_at', 'created_at', 'updated_at'];
    }

    protected $fillable = [
        'uuid',
        'team_id',
        'client_id',
        'case_number',
        'title',
        'description',
        'case_type',
        'status',
        'priority',
        'favorability',
        'court_name',
        'court_type',
        'jurisdiction',
        'judge_name',
        'opposing_party',
        'opposing_counsel',
        'filing_date',
        'next_hearing_at',
        'tags',
        'lead_lawyer_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'client_id' => 'integer',
            'lead_lawyer_id' => 'integer',
            'created_by' => 'integer',
            'case_type' => CaseType::class,
            'status' => CaseStatus::class,
            'priority' => CasePriority::class,
            'favorability' => 'integer',
            'filing_date' => 'date',
            'next_hearing_at' => 'datetime',
            'tags' => 'array',
        ];
    }

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function leadLawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_lawyer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'case_user', 'case_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(Hearing::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class, 'case_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'case_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class, 'case_id')->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class, 'case_id');
    }

    public function aiInsights(): HasMany
    {
        return $this->hasMany(CaseAiInsight::class, 'case_id');
    }

    /* -----------------------------------------------------------------
     |  Domain helpers
     | -----------------------------------------------------------------
     */

    /**
     * The tracking timeline as a plain, oldest-first array — the shape the AI
     * assistants and the insight signature both consume. Uses the already-loaded
     * `events` relation when present to avoid an extra query.
     *
     * @return array<int, array{stage: ?string, title: ?string, sections: array<int, string>, notes: string}>
     */
    public function trackingHistory(): array
    {
        $events = $this->relationLoaded('events') ? $this->events : $this->events()->get();

        return $events
            ->sortBy('id')
            ->map(fn (CaseEvent $e): array => [
                'stage' => $e->stage?->label(),
                'title' => $e->title,
                'sections' => $e->sections ?? [],
                'notes' => $e->description ?? '',
            ])
            ->values()
            ->all();
    }

    /* -----------------------------------------------------------------
     |  Query scopes
     | -----------------------------------------------------------------
     */

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopePriority(Builder $query, ?string $priority): Builder
    {
        return $priority ? $query->where('priority', $priority) : $query;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (CaseStatus $s): string => $s->value,
            CaseStatus::active(),
        ));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('case_number', 'like', "%{$term}%")
                ->orWhere('court_name', 'like', "%{$term}%")
                ->orWhere('opposing_party', 'like', "%{$term}%");
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'case_number' => $this->case_number,
            'title' => $this->title,
            'court_name' => $this->court_name,
            'opposing_party' => $this->opposing_party,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'case_number', 'status', 'priority', 'favorability', 'client_id', 'lead_lawyer_id', 'next_hearing_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('case');
    }
}
