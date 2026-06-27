<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionType;
use App\Models\Document;
use App\Models\User;

/**
 * Authorization for the Documents module. Viewing needs `documents.view`; any
 * write (upload/version/rename/delete) needs `documents.manage`. Tenant ownership
 * is enforced so a user can only act on documents belonging to their firm.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionType::ViewDocuments->value);
    }

    public function view(User $user, Document $document): bool
    {
        return $this->sameTeam($user, $document) && $user->can(PermissionType::ViewDocuments->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionType::ManageDocuments->value);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->sameTeam($user, $document) && $user->can(PermissionType::ManageDocuments->value);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->sameTeam($user, $document) && $user->can(PermissionType::ManageDocuments->value);
    }

    private function sameTeam(User $user, Document $document): bool
    {
        return $user->team_id !== null && $user->team_id === $document->team_id;
    }
}
