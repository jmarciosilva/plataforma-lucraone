<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PermissionController extends Controller
{
    public function __invoke(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Permission::class);

        $permissions = Permission::query()
            ->withCount([
                'roles as roles_count' => fn ($query) => $query->where('role_permission.tenant_id', $context->id()),
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

        return view('permissions.index', [
            'permissions' => $permissions,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [
                ['label' => 'dashboard', 'url' => route('dashboard')],
                ['label' => 'permissões', 'url' => route('roles.index')],
                ['label' => 'catálogo'],
            ],
        ]);
    }
}
