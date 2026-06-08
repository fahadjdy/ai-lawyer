<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\TeamContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Draft/document templates. A template is either firm-owned (team_id set) or a
 * global template (is_global = true, team_id null) seeded for every firm. The
 * default scope therefore allows both the current team's templates and globals.
 */
class LegalTemplate extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'team_id',
        'title',
        'slug',
        'category',
        'description',
        'body',
        'variables',
        'is_global',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'team_id' => 'integer',
            'created_by' => 'integer',
            'variables' => 'array',
            'is_global' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Show the current team's templates plus shared global templates.
        static::addGlobalScope('teamOrGlobal', function (Builder $builder): void {
            $teamId = TeamContext::id();

            if ($teamId !== null) {
                $builder->where(function (Builder $q) use ($teamId): void {
                    $q->where('legal_templates.team_id', $teamId)
                        ->orWhere('legal_templates.is_global', true);
                });
            }
        });

        static::creating(function (LegalTemplate $template): void {
            if (empty($template->team_id) && ! $template->is_global) {
                $template->team_id = TeamContext::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
