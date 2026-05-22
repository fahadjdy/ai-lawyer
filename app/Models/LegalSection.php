<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Global statute reference (acts & sections). Not team-scoped — shared library.
 */
class LegalSection extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'act_name',
        'section_number',
        'title',
        'description',
        'category',
    ];

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('act_name', 'like', "%{$term}%")
                ->orWhere('section_number', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%");
        });
    }
}
