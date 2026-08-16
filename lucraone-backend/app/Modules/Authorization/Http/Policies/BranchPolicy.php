<?php

namespace App\Modules\Authorization\Http\Policies;

use App\Modules\Companies\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id
            && $this->canAccessBranch($user, $branch);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('create-branch');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id
            && $this->canManageBranch($user, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id
            && $this->canManageBranch($user, $branch);
    }

    private function canAccessBranch(User $user, Branch $branch): bool
    {
        if ($user->hasPermission('view-all-branches')) {
            return true;
        }

        if ($user->hasPermission('view-assigned-branches')) {
            return $user->branches()
                ->where('branch_id', $branch->id)
                ->exists();
        }

        return false;
    }

    private function canManageBranch(User $user, Branch $branch): bool
    {
        if ($user->hasPermission('manage-all-branches')) {
            return true;
        }

        if ($user->hasPermission('manage-assigned-branches')) {
            return $user->branches()
                ->where('branch_id', $branch->id)
                ->exists();
        }

        return false;
    }
}
