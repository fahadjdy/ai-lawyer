<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TeamContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope enforcing strict multi-tenant isolation. Every query against a
 * team-owned model is automatically constrained to the currently resolved team,
 * so a stray query can never leak another firm's data.
 */
class TeamScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $teamId = TeamContext::id();

        if ($teamId !== null) {
            $builder->where($model->getTable().'.team_id', $teamId);
        }
    }
}
