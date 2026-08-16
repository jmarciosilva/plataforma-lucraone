<?php

namespace App\Modules\Sales\Http\Policies;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Sales\Domain\Models\Order;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->canAccessTenant($order->tenant_id) && $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->canAccessTenant($order->tenant_id) && $this->canManage($user);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->canAccessTenant($order->tenant_id) && $this->canManage($user);
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyPermission(['view-sales', 'manage-sales', 'create-role']);
    }

    private function canManage(User $user): bool
    {
        return $user->hasAnyPermission(['manage-sales', 'create-role']);
    }
}
