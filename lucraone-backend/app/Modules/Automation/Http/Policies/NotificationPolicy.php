<?php

namespace App\Modules\Automation\Http\Policies;

use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Identity\Domain\Models\User;

/**
 * Aviso é do estabelecimento: qualquer pessoa com vínculo lê e marca como lida.
 * Não há permissão dedicada — esconder um aviso operacional de quem opera
 * derrotaria o propósito.
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notification $notification): bool
    {
        return $user->canAccessTenant($notification->tenant_id)
            && ($notification->user_id === null || $notification->user_id === $user->id);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->view($user, $notification);
    }
}
