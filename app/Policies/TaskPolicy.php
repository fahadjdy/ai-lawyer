<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionType;
use App\Models\Task;
use App\Models\User;

/**
 * Authorization for the Tasks module. Viewing needs `tasks.view`; any write
 * (create/update/delete/move) needs `tasks.manage`. Tenant ownership is also
 * enforced so a user can only act on tasks belonging to their firm.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionType::ViewTasks->value);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->sameTeam($user, $task) && $user->can(PermissionType::ViewTasks->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionType::ManageTasks->value);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->sameTeam($user, $task) && $user->can(PermissionType::ManageTasks->value);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->sameTeam($user, $task) && $user->can(PermissionType::ManageTasks->value);
    }

    private function sameTeam(User $user, Task $task): bool
    {
        return $user->team_id !== null && $user->team_id === $task->team_id;
    }
}
