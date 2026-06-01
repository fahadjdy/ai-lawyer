<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionType;
use App\Models\Hearing;
use App\Models\User;

/**
 * Authorization for the Hearings module. Viewing needs `hearings.view`; any
 * write (schedule/edit/delete) needs `hearings.manage`. Tenant ownership is
 * enforced so a user can only act on hearings belonging to their firm.
 */
class HearingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionType::ViewHearings->value);
    }

    public function view(User $user, Hearing $hearing): bool
    {
        return $this->sameTeam($user, $hearing) && $user->can(PermissionType::ViewHearings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionType::ManageHearings->value);
    }

    public function update(User $user, Hearing $hearing): bool
    {
        return $this->sameTeam($user, $hearing) && $user->can(PermissionType::ManageHearings->value);
    }

    public function delete(User $user, Hearing $hearing): bool
    {
        return $this->sameTeam($user, $hearing) && $user->can(PermissionType::ManageHearings->value);
    }

    private function sameTeam(User $user, Hearing $hearing): bool
    {
        return $user->team_id !== null && $user->team_id === $hearing->team_id;
    }
}
