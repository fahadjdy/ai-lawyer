<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionType;
use App\Enums\RoleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role & rights management — lets a firm administrator control which abilities
 * each role grants. The Firm Owner role is locked to full access so no one can
 * accidentally lock themselves out.
 */
class RoleController extends Controller
{
    /** Friendly labels for each permission module (the prefix before the dot). */
    private const MODULE_LABELS = [
        'cases' => 'Cases',
        'clients' => 'Clients',
        'hearings' => 'Hearings',
        'documents' => 'Documents',
        'evidence' => 'Evidence',
        'tasks' => 'Tasks',
        'templates' => 'Legal Library',
        'team' => 'Firm & Team',
        'audit' => 'Audit Log',
        'settings' => 'Settings',
    ];

    public function index(): Response
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->where('team_id', auth()->user()->team_id)
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => RoleType::tryFrom($r->name)?->label() ?? ucwords(str_replace('_', ' ', $r->name)),
                'color' => RoleType::tryFrom($r->name)?->color() ?? 'slate',
                'locked' => $r->name === RoleType::FirmOwner->value,
                'permissions' => $r->permissions->pluck('name')->values(),
            ]);

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'groups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        // A firm may only edit its own roles (teams-mode scoping).
        abort_unless($role->team_id === $request->user()->team_id, 404);

        abort_if(
            $role->name === RoleType::FirmOwner->value,
            403,
            'The Firm Owner role always has full access and cannot be changed.',
        );

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::in(PermissionType::values())],
        ]);

        $role->syncPermissions($data['permissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $label = RoleType::tryFrom($role->name)?->label() ?? $role->name;

        return back()->with('success', "Permissions updated for {$label}.");
    }

    /**
     * Permissions grouped by module, for the rights matrix.
     *
     * @return array<int, array<string, mixed>>
     */
    private function permissionGroups(): array
    {
        $groups = [];

        foreach (PermissionType::cases() as $permission) {
            [$module, $action] = explode('.', $permission->value, 2);

            $groups[$module] ??= [
                'key' => $module,
                'label' => self::MODULE_LABELS[$module] ?? ucfirst($module),
                'permissions' => [],
            ];

            $groups[$module]['permissions'][] = [
                'name' => $permission->value,
                'label' => ucfirst(str_replace('_', ' ', $action)),
            ];
        }

        return array_values($groups);
    }
}
