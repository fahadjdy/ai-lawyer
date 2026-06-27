<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionType;
use App\Models\Evidence;
use App\Models\User;

/**
 * Authorization for the Evidence module. Viewing needs `evidence.view`; any
 * write (record/edit/delete/custody) needs `evidence.manage`. Tenant ownership
 * is enforced so a user can only act on evidence belonging to their firm.
 */
class EvidencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionType::ViewEvidence->value);
    }

    public function view(User $user, Evidence $evidence): bool
    {
        return $this->sameTeam($user, $evidence) && $user->can(PermissionType::ViewEvidence->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionType::ManageEvidence->value);
    }

    public function update(User $user, Evidence $evidence): bool
    {
        return $this->sameTeam($user, $evidence) && $user->can(PermissionType::ManageEvidence->value);
    }

    public function delete(User $user, Evidence $evidence): bool
    {
        return $this->sameTeam($user, $evidence) && $user->can(PermissionType::ManageEvidence->value);
    }

    private function sameTeam(User $user, Evidence $evidence): bool
    {
        return $user->team_id !== null && $user->team_id === $evidence->team_id;
    }
}
