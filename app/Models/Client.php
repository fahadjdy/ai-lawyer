<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Concerns\BelongsToTeam;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Client extends Model
{
    use BelongsToTeam, HasFactory, HasUuid, LogsActivity, Searchable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'team_id',
        'type',
        'name',
        'company',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'pan',
        'gstin',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
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
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'email', 'phone', 'company'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('client');
    }
}
