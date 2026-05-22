<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionType;
use App\Enums\RoleType;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionType::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach ($this->matrix() as $roleType => $permissions) {
            $role = Role::findOrCreate($roleType, 'web');

            $role->syncPermissions(
                $permissions === '*'
                    ? PermissionType::values()
                    : $permissions,
            );
        }
    }

    /**
     * Role -> permission grant matrix. '*' grants all permissions.
     *
     * @return array<string, string|array<int, string>>
     */
    private function matrix(): array
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
}
