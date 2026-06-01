<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionType;
use App\Models\Client;
use App\Models\User;

/**
 * Authorization for the Clients module — Spatie permission checks combined with
 * tenant ownership (a user may only act on clients belonging to their firm).
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionType::ViewClients->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->sameTeam($user, $client) && $user->can(PermissionType::ViewClients->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionType::CreateClients->value);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->sameTeam($user, $client) && $user->can(PermissionType::UpdateClients->value);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->sameTeam($user, $client) && $user->can(PermissionType::DeleteClients->value);
    }

    private function sameTeam(User $user, Client $client): bool
    {
        return $user->team_id !== null && $user->team_id === $client->team_id;
    }
}
