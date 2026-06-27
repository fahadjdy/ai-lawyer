<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells Spatie which team's roles/permissions to resolve for this request, so
 * the per-tenant RBAC matrix is honoured. Runs after the session is started so
 * the authenticated user (and their team) is resolvable.
 */
class SetPermissionsTeamId
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->team_id);
        }

        return $next($request);
    }
}
