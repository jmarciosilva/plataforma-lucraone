<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebCustomerRequest;
use App\Http\Requests\UpdateWebCustomerRequest;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerWebController extends Controller
{
    private const STATUS = [
        Customer::STATUS_ACTIVE => 'ativo',
        Customer::STATUS_INACTIVE => 'inativo',
    ];

    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = Customer::query()
            ->with('company')
            ->withCount('orders')
            ->when($request->input('trashed') === 'with', fn ($query) => $query->withTrashed())
            ->when($request->input('trashed') === 'only', fn ($query) => $query->onlyTrashed())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->string('company_id')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'companies' => $this->companies(),
            'statusOptions' => self::STATUS,
            'trashedOptions' => $this->trashedOptions(),
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', Customer::class);

        return view('customers.create', [
            'customer' => new Customer(['status' => Customer::STATUS_ACTIVE]),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'novo']],
            'companies' => $this->companies(),
            'statusOptions' => self::STATUS,
        ]);
    }

    public function store(StoreWebCustomerRequest $request, TenantContext $context)
    {
        $customer = Customer::create([
            ...$request->validated(),
            'tenant_id' => $context->id(),
        ]);

        return redirect()
            ->route('sales.customers.show', $customer)
            ->with('sucesso', 'cliente criado.');
    }

    public function show(string $customer, TenantContext $context)
    {
        $customer = Customer::withTrashed()
            ->where('tenant_id', $context->id())
            ->with(['company', 'orders' => fn ($query) => $query->latest()->limit(10)])
            ->findOrFail($customer);

        Gate::authorize('view', $customer);

        return view('customers.show', [
            'customer' => $customer,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $customer->name]],
            'statusRotulos' => OrderWebController::STATUS_ROTULOS,
        ]);
    }

    public function edit(Customer $customer, TenantContext $context)
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $customer->name, 'url' => route('sales.customers.show', $customer)], ['label' => 'editar']],
            'companies' => $this->companies(),
            'statusOptions' => self::STATUS,
        ]);
    }

    public function update(UpdateWebCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return redirect()
            ->route('sales.customers.show', $customer)
            ->with('sucesso', 'cliente atualizado.');
    }

    public function destroy(Customer $customer)
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('sales.customers.index', ['trashed' => 'with'])
            ->with('sucesso', 'cliente arquivado.');
    }

    public function restore(string $customer, TenantContext $context)
    {
        $customer = Customer::onlyTrashed()
            ->where('tenant_id', $context->id())
            ->findOrFail($customer);

        Gate::authorize('restore', $customer);

        $customer->restore();

        return redirect()
            ->route('sales.customers.show', $customer)
            ->with('sucesso', 'cliente restaurado.');
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'clientes', 'url' => route('sales.customers.index')],
        ];
    }

    private function companies()
    {
        return Company::query()
            ->where('status', 'ACTIVE')
            ->orderBy('trade_name')
            ->orderBy('legal_name')
            ->get();
    }

    private function trashedOptions(): array
    {
        return [
            '' => 'ativos',
            'with' => 'ativos e arquivados',
            'only' => 'somente arquivados',
        ];
    }
}
