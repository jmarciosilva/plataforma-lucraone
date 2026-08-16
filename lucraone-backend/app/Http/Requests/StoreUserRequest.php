<?php

namespace App\Http\Requests;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', User::class)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        $tenantId = $context->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId) {
                    $jaVinculado = User::withTrashed()
                        ->where('email', $value)
                        ->whereHas('memberships', fn ($query) => $query->where('tenant_id', $tenantId))
                        ->exists();

                    if ($jaVinculado) {
                        $fail('este e-mail já está vinculado a este estabelecimento.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in($this->statusPermitidos())],
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
