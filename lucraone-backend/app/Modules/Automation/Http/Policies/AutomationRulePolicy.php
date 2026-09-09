<?php

namespace App\Modules\Automation\Http\Policies;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Identity\Domain\Models\User;

class AutomationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, AutomationRule $rule): bool
    {
        return $user->canAccessTenant($rule->tenant_id) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, AutomationRule $rule): bool
    {
        return $user->canAccessTenant($rule->tenant_id) && $this->canManage($user);
    }

    public function delete(User $user, AutomationRule $rule): bool
    {
        return $user->canAccessTenant($rule->tenant_id) && $this->canManage($user);
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyPermission(['view-automations', 'manage-automations', 'create-role']);
    }

    /**
     * Automação muda dado de negócio sozinha — reprecifica produto, dispara
     * e-mail. Criar regra é privilégio de quem administra, não de quem opera.
     */
    private function canManage(User $user): bool
    {
        return $user->hasAnyPermission(['manage-automations', 'create-role']);
    }
}
