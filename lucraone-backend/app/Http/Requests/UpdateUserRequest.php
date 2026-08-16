<?php

namespace App\Http\Requests;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = User::withTrashed()->findOrFail($this->route('user'));

        return $this->user()
            ? Gate::forUser($this->user())->allows('update', $user)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        $tenantId = $context->id();
        $userId = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'account_status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'status' => ['required', Rule::in($this->statusPermitidos())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists(Role::class, 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    /**
     * O status gerenciado nesta tela é o vínculo no estabelecimento atual.
     */
    private function statusPermitidos(): array
    {
        return [
            TenantUser::STATUS_ACTIVE,
            TenantUser::STATUS_INVITED,
            TenantUser::STATUS_INACTIVE,
            TenantUser::STATUS_SUSPENDED,
        ];
    }
}
