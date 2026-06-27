<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts Spatie's permission tables to "teams" mode so each firm gets its own
 * role → permission matrix (fixing cross-tenant RBAC bleed). The pivot tables'
 * primary keys must include team_id, which can't be ALTERed portably, so we
 * capture existing data, rebuild the tables in teams shape, and repopulate one
 * role-set per team — re-mapping every user to their previous role.
 *
 * Idempotent: on a fresh install the original migration already builds the
 * teams shape (config teams=true), so this becomes a no-op.
 *
 * ⚠️ Back up the database before running on production.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tn = config('permission.table_names');
        $cn = config('permission.column_names');
        $teamKey = $cn['team_foreign_key'];
        $pivotRole = $cn['role_pivot_key'] ?? 'role_id';
        $pivotPerm = $cn['permission_pivot_key'] ?? 'permission_id';
        $morphKey = $cn['model_morph_key'];

        // Already in teams shape → nothing to do.
        if (Schema::hasColumn($tn['roles'], $teamKey)) {
            return;
        }

        // ---- 1. Capture existing (global) data ----
        $roles = DB::table($tn['roles'])->get();
        $roleById = $roles->keyBy('id');
        $permNameById = DB::table($tn['permissions'])->pluck('name', 'id');
        $permIdByName = DB::table($tn['permissions'])->pluck('id', 'name');
        $rolePerms = DB::table($tn['role_has_permissions'])->get();
        $modelRoles = DB::table($tn['model_has_roles'])->get();
        $userTeam = DB::table('users')->pluck('team_id', 'id');

        $roleNamePerms = [];
        foreach ($rolePerms as $rp) {
            $rn = $roleById[$rp->role_id]->name ?? null;
            $pn = $permNameById[$rp->permission_id] ?? null;
            if ($rn && $pn) {
                $roleNamePerms[$rn][$pn] = true;
            }
        }
        $roleNames = $roles->pluck('name')->unique()->values();

        // ---- 2. Rebuild the four tables in teams shape (permissions table kept) ----
        Schema::dropIfExists($tn['role_has_permissions']);
        Schema::dropIfExists($tn['model_has_roles']);
        Schema::dropIfExists($tn['model_has_permissions']);
        Schema::dropIfExists($tn['roles']);

        Schema::create($tn['roles'], function (Blueprint $t) use ($teamKey) {
            $t->id();
            $t->unsignedBigInteger($teamKey)->nullable();
            $t->index($teamKey, 'roles_team_foreign_key_index');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique([$teamKey, 'name', 'guard_name']);
        });

        Schema::create($tn['model_has_permissions'], function (Blueprint $t) use ($tn, $morphKey, $pivotPerm, $teamKey) {
            $t->unsignedBigInteger($pivotPerm);
            $t->string('model_type');
            $t->unsignedBigInteger($morphKey);
            $t->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $t->foreign($pivotPerm)->references('id')->on($tn['permissions'])->cascadeOnDelete();
            $t->unsignedBigInteger($teamKey);
            $t->index($teamKey, 'model_has_permissions_team_foreign_key_index');
            $t->primary([$teamKey, $pivotPerm, $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tn['model_has_roles'], function (Blueprint $t) use ($tn, $morphKey, $pivotRole, $teamKey) {
            $t->unsignedBigInteger($pivotRole);
            $t->string('model_type');
            $t->unsignedBigInteger($morphKey);
            $t->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $t->foreign($pivotRole)->references('id')->on($tn['roles'])->cascadeOnDelete();
            $t->unsignedBigInteger($teamKey);
            $t->index($teamKey, 'model_has_roles_team_foreign_key_index');
            $t->primary([$teamKey, $pivotRole, $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create($tn['role_has_permissions'], function (Blueprint $t) use ($tn, $pivotRole, $pivotPerm) {
            $t->unsignedBigInteger($pivotPerm);
            $t->unsignedBigInteger($pivotRole);
            $t->foreign($pivotPerm)->references('id')->on($tn['permissions'])->cascadeOnDelete();
            $t->foreign($pivotRole)->references('id')->on($tn['roles'])->cascadeOnDelete();
            $t->primary([$pivotPerm, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        // ---- 3. Recreate roles per team, reattach permissions, reassign users ----
        $teamIds = DB::table('teams')->pluck('id');
        $now = now();
        $newRoleId = [];

        foreach ($teamIds as $teamId) {
            foreach ($roleNames as $name) {
                $id = DB::table($tn['roles'])->insertGetId([
                    $teamKey => $teamId,
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $newRoleId["{$teamId}|{$name}"] = $id;

                foreach (array_keys($roleNamePerms[$name] ?? []) as $permName) {
                    $pid = $permIdByName[$permName] ?? null;
                    if ($pid) {
                        DB::table($tn['role_has_permissions'])->insert([
                            $pivotPerm => $pid,
                            $pivotRole => $id,
                        ]);
                    }
                }
            }
        }

        foreach ($modelRoles as $mr) {
            $name = $roleById[$mr->role_id]->name ?? null;
            $teamId = $userTeam[$mr->model_id] ?? null;
            $rid = ($name && $teamId) ? ($newRoleId["{$teamId}|{$name}"] ?? null) : null;
            if (! $rid) {
                continue;
            }
            DB::table($tn['model_has_roles'])->insert([
                $pivotRole => $rid,
                'model_type' => $mr->model_type,
                $morphKey => $mr->model_id,
                $teamKey => $teamId,
            ]);
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Structural, data-rewriting change — not reversible.
    }
};
