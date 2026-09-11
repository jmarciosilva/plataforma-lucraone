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

    /**
     * Dados da identidade global: nome, e-mail, senha, status da conta,
     * arquivamento e restauração.
     *
     * manage-users é permissão de um estabelecimento. Só alcança a identidade
     * de quem pertence exclusivamente a ele — nunca quem também existe em
     * outro, nem o Platform Admin.
     */
    public function manageGlobalIdentity(User $user, User $target, string $tenantId): bool
    {
        return $user->hasPermission('manage-users', $tenantId)
            && $target->pertenceExclusivamenteAo($tenantId);
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
