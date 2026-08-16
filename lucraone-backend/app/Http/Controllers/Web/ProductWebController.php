<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebPriceRequest;
use App\Http\Requests\StoreWebProductRequest;
use App\Http\Requests\UpdateWebProductRequest;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\PriceHistory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductWebController extends Controller
{
    private const STATUS = [
        'active' => 'active',
        'inactive' => 'inactive',
        'discontinued' => 'discontinued',
    ];

    private const PRICE_TYPES = [
        Price::TYPE_COST => 'custo',
        Price::TYPE_SALE => 'venda',
        Price::TYPE_SUGGESTED_RETAIL => 'sugerido',
    ];

    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['company', 'categories', 'prices'])
            ->withCount('categories')
            ->when($request->input('trashed') === 'with', fn ($query) => $query->withTrashed())
            ->when($request->input('trashed') === 'only', fn ($query) => $query->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->string('company_id')->toString()));

        match ($request->input('sort', 'created_desc')) {
            'name' => $products->orderBy('name'),
            'sku' => $products->orderBy('sku'),
            'status' => $products->orderBy('status')->orderBy('name'),
            'created_asc' => $products->orderBy('created_at'),
            default => $products->latest(),
        };

        return view('products.index', [
            'products' => $products->paginate(20)->withQueryString(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'statusOptions' => self::STATUS,
            'sortOptions' => $this->sortOptions(),
            'trashedOptions' => $this->trashedOptions(),
            'companies' => $this->companies(),
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', Product::class);

        return view('products.create', [
            'product' => new Product(['status' => 'active']),
            'selectedCategories' => [],
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'novo']],
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreWebProductRequest $request, TenantContext $context)
    {
        $product = Product::create([
            ...$request->safe()->except('category_ids'),
            'tenant_id' => $context->id(),
        ]);

        $product->categories()->sync($request->input('category_ids', []));

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('sucesso', 'produto criado.');
    }

    public function show(string $product, TenantContext $context)
    {
        $product = Product::withTrashed()
            ->where('tenant_id', $context->id())
            ->findOrFail($product);

        Gate::authorize('view', $product);

        $product->load(['company', 'categories', 'prices']);

        return view('products.show', [
            'product' => $product,
            'priceTypes' => self::PRICE_TYPES,
            'priceHistory' => PriceHistory::query()
                ->where('product_id', $product->id)
                ->with('changedBy')
                ->latest('changed_at')
                ->limit(10)
                ->get(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $product->name]],
        ]);
    }

    public function edit(Product $product, TenantContext $context)
    {
        Gate::authorize('update', $product);

        return view('products.edit', [
            'product' => $product,
            'selectedCategories' => $product->categories()->pluck('categories.id')->all(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $product->name, 'url' => route('catalog.products.show', $product)], ['label' => 'editar']],
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateWebProductRequest $request, Product $product)
    {
        $product->update($request->safe()->except('category_ids'));
        $product->categories()->sync($request->input('category_ids', []));

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('sucesso', 'produto atualizado.');
    }

    public function destroy(Product $product)
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('catalog.products.index', ['trashed' => 'with'])
            ->with('sucesso', 'produto arquivado.');
    }

    public function restore(string $product, TenantContext $context)
    {
        $product = Product::onlyTrashed()
            ->where('tenant_id', $context->id())
            ->findOrFail($product);

        Gate::authorize('restore', $product);

        $product->restore();

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('sucesso', 'produto restaurado.');
    }

    public function storePrice(StoreWebPriceRequest $request, Product $product, TenantContext $context)
    {
        Gate::authorize('update', $product);

        $price = Price::query()
            ->where('tenant_id', $context->id())
            ->where('product_id', $product->id)
            ->where('currency', strtoupper($request->validated('currency')))
            ->where('type', $request->validated('type'))
            ->first();

        $oldAmount = $price?->amount;

        $price = Price::updateOrCreate(
            [
                'tenant_id' => $context->id(),
                'product_id' => $product->id,
                'currency' => strtoupper($request->validated('currency')),
                'type' => $request->validated('type'),
            ],
            ['amount' => $request->validated('amount')]
        );

        if ($oldAmount !== null && (float) $oldAmount !== (float) $price->amount) {
            PriceHistory::create([
                'tenant_id' => $context->id(),
                'price_id' => $price->id,
                'product_id' => $product->id,
                'old_amount' => $oldAmount,
                'new_amount' => $price->amount,
                'currency' => $price->currency,
                'changed_by' => $request->user()?->id,
                'reason' => $request->validated('reason') ?: 'alteração pelo painel',
                'changed_at' => now(),
            ]);
        }

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('sucesso', 'preço salvo.');
    }

    public function destroyPrice(Product $product, Price $price)
    {
        Gate::authorize('update', $product);

        abort_unless($price->product_id === $product->id, 404);

        $price->delete();

        return redirect()
            ->route('catalog.products.show', $product)
            ->with('sucesso', 'preço removido.');
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'produtos', 'url' => route('catalog.products.index')],
        ];
    }

    private function formOptions(): array
    {
        return [
            'statusOptions' => self::STATUS,
            'companies' => $this->companies(),
            'categories' => Category::query()
                ->with('parent')
                ->orderBy('name')
                ->get(),
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

    private function sortOptions(): array
    {
        return [
            'created_desc' => 'mais recentes',
            'created_asc' => 'mais antigos',
            'name' => 'nome',
            'sku' => 'sku',
            'status' => 'status',
        ];
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
