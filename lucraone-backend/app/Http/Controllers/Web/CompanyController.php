<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Modules\Companies\Domain\Models\Address;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    private const STATUS = [
        'ACTIVE' => 'active',
        'INACTIVE' => 'inactive',
        'SUSPENDED' => 'suspended',
    ];

    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Company::class);

        $companies = Company::query()
            ->withCount(['addresses', 'branches'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('legal_name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));

        match ($request->input('sort', 'created_desc')) {
            'legal_name' => $companies->orderBy('legal_name'),
            'trade_name' => $companies->orderBy('trade_name')->orderBy('legal_name'),
            'status' => $companies->orderBy('status')->orderBy('legal_name'),
            'created_asc' => $companies->orderBy('created_at'),
            default => $companies->latest(),
        };

        return view('companies.index', [
            'companies' => $companies->paginate(20)->withQueryString(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'statusOptions' => self::STATUS,
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', Company::class);

        return view('companies.create', [
            'company' => new Company(['status' => 'ACTIVE']),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'nova']],
            'statusOptions' => self::STATUS,
        ]);
    }

    public function store(StoreCompanyRequest $request, TenantContext $context)
    {
        $company = Company::create([
            ...$request->validated(),
            'id' => (string) Str::ulid(),
            'tenant_id' => $context->id(),
        ]);

        return redirect()
            ->route('companies.show', $company)
            ->with('sucesso', 'empresa criada.');
    }

    public function show(Company $company, TenantContext $context)
    {
        Gate::authorize('view', $company);

        $company->load(['addresses' => fn ($query) => $query->latest('is_primary')->latest()]);

        return view('companies.show', [
            'company' => $company,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $company->trade_name ?: $company->legal_name]],
        ]);
    }

    public function edit(Company $company, TenantContext $context)
    {
        Gate::authorize('update', $company);

        return view('companies.edit', [
            'company' => $company,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $company->trade_name ?: $company->legal_name, 'url' => route('companies.show', $company)], ['label' => 'editar']],
            'statusOptions' => self::STATUS,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());

        return redirect()
            ->route('companies.show', $company)
            ->with('sucesso', 'empresa atualizada.');
    }

    public function destroy(Company $company)
    {
        Gate::authorize('delete', $company);

        $company->update(['status' => 'INACTIVE']);

        return redirect()
            ->route('companies.index', ['status' => 'INACTIVE'])
            ->with('sucesso', 'empresa desativada.');
    }

    public function restore(Company $company)
    {
        Gate::authorize('restore', $company);

        $company->update(['status' => 'ACTIVE']);

        return redirect()
            ->route('companies.show', $company)
            ->with('sucesso', 'empresa reativada.');
    }

    public function storeAddress(StoreAddressRequest $request, Company $company, TenantContext $context)
    {
        Gate::authorize('update', $company);

        $this->desmarcarPrimariosSeNecessario($company, $request->boolean('is_primary'));

        $company->addresses()->create([
            ...$request->validated(),
            'id' => (string) Str::ulid(),
            'tenant_id' => $context->id(),
            'country' => strtoupper($request->validated('country')),
            'state' => strtoupper($request->validated('state')),
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return redirect()
            ->route('companies.show', $company)
            ->with('sucesso', 'endereço criado.');
    }

    public function updateAddress(StoreAddressRequest $request, Company $company, Address $address)
    {
        Gate::authorize('update', $company);

        abort_unless($address->addressable_type === $company->getMorphClass() && $address->addressable_id === $company->id, 404);

        $this->desmarcarPrimariosSeNecessario($company, $request->boolean('is_primary'), $address->id);

        $address->update([
            ...$request->validated(),
            'country' => strtoupper($request->validated('country')),
            'state' => strtoupper($request->validated('state')),
            'is_primary' => $request->boolean('is_primary'),
        ]);

        return redirect()
            ->route('companies.show', $company)
            ->with('sucesso', 'endereço atualizado.');
    }

    private function desmarcarPrimariosSeNecessario(Company $company, bool $novoPrimario, ?string $ignorarId = null): void
    {
        if (! $novoPrimario) {
            return;
        }

        $query = $company->addresses()->where('is_primary', true);

        if ($ignorarId) {
            $query->whereKeyNot($ignorarId);
        }

        $query->update(['is_primary' => false]);
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'empresas', 'url' => route('companies.index')],
        ];
    }

    private function sortOptions(): array
    {
        return [
            'created_desc' => 'mais recentes',
            'created_asc' => 'mais antigas',
            'legal_name' => 'razão social',
            'trade_name' => 'nome fantasia',
            'status' => 'status',
        ];
    }
}
