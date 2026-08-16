<?php

namespace App\Modules\Identity\Http\Policies;

use App\Modules\Identity\Domain\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewUsers($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->canViewUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->canManageUsers($user);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->canManageUsers($user);
    }

    public function restore(User $user, User $target): bool
    {
        return $this->canManageUsers($user);
    }

    private function canViewUsers(User $user): bool
    {
        return $user->hasAnyPermission(['view-users', 'manage-users']);
    }

    private function canManageUsers(User $user): bool
    {
        return $user->hasPermission('manage-users');
    }
}
