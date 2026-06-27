<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PermissionType;
use App\Enums\RoleType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for the role → permission matrix, and the helper that
 * provisions a fresh firm's own set of roles. With Spatie "teams" mode enabled,
 * roles are per-team, so every firm gets its own editable copy of the matrix.
 */
class RolePermissions
{
    /**
     * Role slug → granted permissions ('*' = all).
     *
     * @return array<string, string|array<int, string>>
     */
    public static function matrix(): array
    {
        return [
            RoleType::FirmOwner->value => '*',
            RoleType::Partner->value => '*',

            RoleType::Associate->value => [
                PermissionType::ViewCases->value,
                PermissionType::CreateCases->value,
                PermissionType::UpdateCases->value,
                PermissionType::ViewClients->value,
                PermissionType::CreateClients->value,
                PermissionType::UpdateClients->value,
                PermissionType::ViewHearings->value,
                PermissionType::ManageHearings->value,
                PermissionType::ViewDocuments->value,
                PermissionType::ManageDocuments->value,
                PermissionType::ViewEvidence->value,
                PermissionType::ManageEvidence->value,
                PermissionType::ViewTasks->value,
                PermissionType::ManageTasks->value,
                PermissionType::ViewTemplates->value,
            ],

            RoleType::Paralegal->value => [
                PermissionType::ViewCases->value,
                PermissionType::ViewClients->value,
                PermissionType::ViewHearings->value,
                PermissionType::ViewDocuments->value,
                PermissionType::ManageDocuments->value,
                PermissionType::ViewEvidence->value,
                PermissionType::ManageEvidence->value,
                PermissionType::ViewTasks->value,
                PermissionType::ManageTasks->value,
                PermissionType::ViewTemplates->value,
            ],

            RoleType::Clerk->value => [
                PermissionType::ViewCases->value,
                PermissionType::ViewClients->value,
                PermissionType::ViewHearings->value,
                PermissionType::ViewDocuments->value,
                PermissionType::ViewTasks->value,
            ],
        ];
    }

    /**
     * Ensure every permission exists (permissions are global, not per-team).
     */
    public static function ensurePermissions(): void
    {
        foreach (PermissionType::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }
    }

    /**
     * Create (or refresh) this firm's own roles with the default matrix.
     */
    public static function provision(int $teamId): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($teamId);

        try {
            foreach (self::matrix() as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($permissions === '*' ? PermissionType::values() : $permissions);
            }
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
