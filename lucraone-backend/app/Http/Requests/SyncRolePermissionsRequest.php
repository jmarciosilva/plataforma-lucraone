<?php

namespace App\Http\Requests;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $this->user() && $role
            ? Gate::forUser($this->user())->allows('update', $role)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists(Permission::class, 'id')->where('tenant_id', $context->id()),
            ],
        ];
    }
}
