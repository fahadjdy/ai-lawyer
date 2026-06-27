<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionType;
use App\Models\LegalCase;
use App\Models\User;

/**
 * Authorization for the Cases module. Combines Spatie permission checks with
 * tenant ownership: a user may only act on cases belonging to their firm.
 */
class CasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionType::ViewCases->value);
    }

    public function view(User $user, LegalCase $case): bool
    {
        return $this->sameTeam($user, $case) && $user->can(PermissionType::ViewCases->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionType::CreateCases->value);
    }

    public function update(User $user, LegalCase $case): bool
    {
        return $this->sameTeam($user, $case) && $user->can(PermissionType::UpdateCases->value);
    }

    public function delete(User $user, LegalCase $case): bool
    {
        return $this->sameTeam($user, $case) && $user->can(PermissionType::DeleteCases->value);
    }

    public function restore(User $user, LegalCase $case): bool
    {
        return $this->sameTeam($user, $case) && $user->can(PermissionType::DeleteCases->value);
    }

    public function forceDelete(User $user, LegalCase $case): bool
    {
        return $this->sameTeam($user, $case) && $user->can(PermissionType::DeleteCases->value);
    }

    public function assign(User $user, LegalCase $case): bool
    {
        return $this->sameTeam($user, $case) && $user->can(PermissionType::AssignCases->value);
    }

    private function sameTeam(User $user, LegalCase $case): bool
    {
        return $user->team_id !== null && $user->team_id === $case->team_id;
    }
}
