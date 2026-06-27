<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use App\Support\RolePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the global permission catalogue, then provisions each existing firm's
 * own per-team roles (Spatie teams mode). New firms get their roles provisioned
 * at creation time (registration / DemoSeeder) via {@see RolePermissions}.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        RolePermissions::ensurePermissions();

        foreach (Team::all() as $team) {
            RolePermissions::provision($team->id);
        }
    }
}
