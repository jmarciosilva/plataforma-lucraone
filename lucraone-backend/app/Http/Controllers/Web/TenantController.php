<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    private const STATUS = [
        'TRIAL' => 'trial',
        'ACTIVE' => 'active',
        'SUSPENDED' => 'suspended',
        'CANCELLED' => 'cancelled',
    ];

    private const PLANOS = [
        'free' => 'free',
        'standard' => 'standard',
        'enterprise' => 'enterprise',
    ];

    private const TIMEZONES = [
        'America/Sao_Paulo' => 'America/Sao_Paulo',
        'America/Manaus' => 'America/Manaus',
        'America/Bahia' => 'America/Bahia',
        'UTC' => 'UTC',
    ];

    private const LOCALES = [
        'pt-BR' => 'pt-BR',
        'en-US' => 'en-US',
    ];

    private const MOEDAS = [
        'BRL' => 'BRL',
        'USD' => 'USD',
    ];

    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Tenant::class);

        $tenants = Tenant::query()
            ->withCount(['companies', 'activeUsers'])
            ->when($request->input('trashed') === 'with', fn ($query) => $query->withTrashed())
            ->when($request->input('trashed') === 'only', fn ($query) => $query->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));

        $sort = $request->input('sort', 'created_desc');
        match ($sort) {
            'name' => $tenants->orderBy('name'),
            'status' => $tenants->orderBy('status')->orderBy('name'),
            'created_asc' => $tenants->orderBy('created_at'),
            default => $tenants->latest(),
        };

        return view('tenants.index', [
            'tenants' => $tenants->paginate(20)->withQueryString(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'statusOptions' => self::STATUS,
            'sortOptions' => [
                'created_desc' => 'mais recentes',
                'created_asc' => 'mais antigos',
                'name' => 'nome',
                'status' => 'status',
            ],
            'trashedOptions' => [
                '' => 'ativos',
                'with' => 'ativos e arquivados',
                'only' => 'somente arquivados',
            ],
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', Tenant::class);

        return view('tenants.create', [
            'tenant' => new Tenant([
                'status' => 'TRIAL',
                'plan' => 'free',
                'timezone' => 'America/Sao_Paulo',
                'locale' => 'pt-BR',
                'currency' => 'BRL',
            ]),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'novo']],
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreTenantRequest $request)
    {
        $tenant = DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                ...$request->validated(),
                'id' => (string) Str::ulid(),
                'active' => $this->activeFromStatus($request->validated('status')),
            ]);

            $request->user()->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);
            $this->provisionarAutorizacaoPadrao($tenant, $request->user());

            return $tenant;
        });

        return redirect()
            ->route('tenants.show', $tenant)
            ->with('sucesso', 'estabelecimento criado.');
    }

    public function show(string $tenant, TenantContext $context)
    {
        $tenant = Tenant::withTrashed()
            ->withCount(['companies', 'activeUsers'])
            ->findOrFail($tenant);

        Gate::authorize('view', $tenant);

        return view('tenants.show', [
            'tenant' => $tenant,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $tenant->name]],
        ]);
    }

    public function edit(string $tenant, TenantContext $context)
    {
        $tenant = Tenant::withTrashed()->findOrFail($tenant);

        Gate::authorize('update', $tenant);

        return view('tenants.edit', [
            'tenant' => $tenant,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $tenant->name, 'url' => route('tenants.show', $tenant)], ['label' => 'editar']],
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateTenantRequest $request, string $tenant)
    {
        $tenant = Tenant::withTrashed()->findOrFail($tenant);

        $tenant->update([
            ...$request->validated(),
            'active' => $this->activeFromStatus($request->validated('status')),
        ]);

        return redirect()
            ->route('tenants.show', $tenant)
            ->with('sucesso', 'estabelecimento atualizado.');
    }

    public function destroy(Request $request, string $tenant, TenantContext $context)
    {
        $tenant = Tenant::findOrFail($tenant);

        Gate::authorize('delete', $tenant);

        if ($tenant->id === $context->id()) {
            return back()->with('erro', 'não é possível arquivar o estabelecimento em uso.');
        }

        $tenant->delete();

        return redirect()
            ->route('tenants.index', ['trashed' => 'with'])
            ->with('sucesso', 'estabelecimento arquivado.');
    }

    public function restore(Request $request, string $tenant)
    {
        $tenant = Tenant::onlyTrashed()->findOrFail($tenant);

        Gate::authorize('restore', $tenant);

        $tenant->restore();

        return redirect()
            ->route('tenants.show', $tenant)
            ->with('sucesso', 'estabelecimento restaurado.');
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'tenants', 'url' => route('tenants.index')],
        ];
    }

    private function formOptions(): array
    {
        return [
            'statusOptions' => self::STATUS,
            'planoOptions' => self::PLANOS,
            'timezoneOptions' => self::TIMEZONES,
            'localeOptions' => self::LOCALES,
            'moedaOptions' => self::MOEDAS,
        ];
    }

    private function activeFromStatus(string $status): bool
    {
        return in_array($status, ['TRIAL', 'ACTIVE'], true);
    }

    private function provisionarAutorizacaoPadrao(Tenant $tenant, $usuario): void
    {
        $permissions = collect([
            'create-role', 'update-role', 'delete-role', 'view-roles',
            'create-permission', 'update-permission', 'delete-permission', 'view-permissions',
            'manage-companies', 'view-companies',
            'manage-products', 'view-products',
            'manage-users', 'view-users',
            'manage-branches', 'view-branches', 'view-all-branches',
        ])->mapWithKeys(function (string $name) use ($tenant) {
            $permission = Permission::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['id' => (string) Str::ulid(), 'description' => $name]
            );

            return [$name => $permission];
        });

        $admin = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'admin'],
            ['id' => (string) Str::ulid(), 'description' => 'Administrator role with full access']
        );

        foreach ($permissions as $permission) {
            $admin->grantPermission($permission);
        }

        $usuario->assignRole($admin, $tenant->id);
    }
}
