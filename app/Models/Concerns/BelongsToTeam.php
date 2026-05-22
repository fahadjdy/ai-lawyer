<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\TeamScope;
use App\Models\Team;
use App\Support\TeamContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as team-owned. Applies the {@see TeamScope} global scope for
 * tenant isolation and auto-stamps `team_id` on creation from the current
 * team context, so callers never have to set it manually.
 */
trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope(new TeamScope);

        static::creating(function ($model): void {
            if (empty($model->team_id) && ($teamId = TeamContext::id()) !== null) {
                $model->team_id = $teamId;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
