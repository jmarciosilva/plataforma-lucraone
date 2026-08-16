<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount([
                'permissions as permissions_count' => fn ($query) => $query->where('role_permission.tenant_id', $context->id()),
                'users as users_count' => fn ($query) => $query->where('user_role.tenant_id', $context->id()),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('roles.index', [
            'roles' => $roles,
            'permissionsCount' => Permission::query()->count(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function show(Role $role, TenantContext $context)
    {
        Gate::authorize('view', $role);

        return view('roles.show', [
            'role' => $role,
            'permissions' => Permission::query()->orderBy('name')->get(),
            'selectedPermissions' => $role->permissionsForTenant()->pluck('permissions.id')->all(),
            'users' => $role->usersForTenant()->orderBy('name')->get(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $role->name]],
        ]);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role, TenantContext $context)
    {
        Gate::authorize('update', $role);

        $permissionIds = Permission::query()
            ->whereIn('id', $request->validated('permissions', []))
            ->pluck('id');

        DB::transaction(function () use ($role, $context, $permissionIds) {
            $role->permissions()
                ->wherePivot('tenant_id', $context->id())
                ->detach();

            foreach ($permissionIds as $permissionId) {
                $role->permissions()->attach($permissionId, ['tenant_id' => $context->id()]);
            }
        });

        return redirect()
            ->route('roles.show', $role)
            ->with('sucesso', 'permissões atualizadas.');
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'permissões', 'url' => route('roles.index')],
        ];
    }
}
