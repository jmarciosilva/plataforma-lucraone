<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustWebInventoryRequest;
use App\Http\Requests\StoreWebStockLevelRequest;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryWebController extends Controller
{
    private const MOVEMENT_TYPES = [
        InventoryMovement::TYPE_IN => 'entrada',
        InventoryMovement::TYPE_OUT => 'saída',
        InventoryMovement::TYPE_ADJUSTMENT => 'ajuste',
        InventoryMovement::TYPE_RESERVATION => 'reserva',
        InventoryMovement::TYPE_RELEASE => 'liberação',
    ];

    public function __construct(
        private InventoryAdjustmentService $adjustments
    ) {}

    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Inventory::class);

        $inventory = Inventory::query()
            ->with(['product.categories', 'company', 'stockLevel'])
            ->when($request->filled('filter_company_id'), fn ($query) => $query->where('company_id', $request->string('filter_company_id')->toString()))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->string('product_id')->toString()))
            ->when($request->filled('filter_category_id'), function ($query) use ($request) {
                $query->whereHas('product.categories', fn ($query) => $query->whereKey($request->string('filter_category_id')->toString()));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->whereHas('product', function ($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->input('filter_status', $request->input('status')) === 'low', function ($query) {
                $query->whereHas('stockLevel', fn ($query) => $query->whereColumn('inventories.quantity_on_hand', '<=', 'stock_levels.reorder_point'));
            })
            ->when($request->input('filter_status', $request->input('status')) === 'over', function ($query) {
                $query->whereHas('stockLevel', fn ($query) => $query->whereNotNull('max_qty')->whereColumn('inventories.quantity_on_hand', '>', 'stock_levels.max_qty'));
            })
            ->when($request->input('filter_status', $request->input('status')) === 'available', fn ($query) => $query->whereRaw('quantity_on_hand > reserved'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('inventory.index', [
            'inventory' => $inventory,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'companies' => $this->companies(),
            'categories' => Category::query()->orderBy('name')->get(),
            'products' => $this->products(),
            'packageOptions' => $this->packageOptions(),
            'movementTypes' => self::MOVEMENT_TYPES,
            'statusOptions' => $this->statusOptions(),
            'summary' => $this->summary(),
        ]);
    }

    public function show(string $inventory, TenantContext $context)
    {
        $inventory = Inventory::query()
            ->with(['product.categories', 'company', 'stockLevel'])
            ->findOrFail($inventory);

        Gate::authorize('view', $inventory);

        return view('inventory.show', [
            'inventory' => $inventory,
            'movements' => InventoryMovement::query()
                ->where('inventory_id', $inventory->id)
                ->with('user')
                ->latest('moved_at')
                ->paginate(15),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $inventory->product->name]],
            'movementTypes' => self::MOVEMENT_TYPES,
        ]);
    }

    public function adjust(AdjustWebInventoryRequest $request)
    {
        $product = Product::query()->findOrFail($request->validated('product_id'));
        $package = $request->filled('package_id')
            ? ProductPackage::query()->findOrFail($request->validated('package_id'))
            : null;

        // O estoque só conhece a unidade base: a embalagem é convertida aqui,
        // antes do serviço, e a conversão fica registrada no motivo.
        $quantity = $package
            ? (int) $request->validated('quantity') * $package->factor
            : (float) $request->validated('quantity');

        $reason = $package
            ? $this->packageReason($request->validated('type'), (int) $request->validated('quantity'), $package, $product, $request->validated('reason'))
            : $request->validated('reason');

        try {
            $inventory = $this->adjustments->adjust(
                $product,
                $request->validated('company_id'),
                $request->validated('type'),
                (float) $quantity,
                $reason,
                $request->user()?->id
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['quantity' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('inventory.show', $inventory)
            ->with('sucesso', 'estoque atualizado.');
    }

    public function storeStockLevel(StoreWebStockLevelRequest $request, TenantContext $context)
    {
        $stockLevel = StockLevel::query()->updateOrCreate(
            [
                'tenant_id' => $context->id(),
                'product_id' => $request->validated('product_id'),
                'company_id' => $request->validated('company_id'),
            ],
            [
                'min_qty' => $request->validated('min_qty'),
                'max_qty' => $request->validated('max_qty'),
                'reorder_point' => $request->validated('reorder_point'),
            ]
        );

        return redirect()
            ->route('inventory.index', ['product_id' => $stockLevel->product_id])
            ->with('sucesso', 'níveis de estoque salvos.');
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'estoque', 'url' => route('inventory.index')],
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

    private function products()
    {
        return Product::query()
            ->with('company')
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Ex.: "entrada por embalagem: 2 × Caixa 24 = 48 UN · compra fornecedor".
     * O motivo do usuário é preservado; o conjunto respeita as 255 posições da coluna.
     */
    private function packageReason(string $type, int $packages, ProductPackage $package, Product $product, ?string $reason): string
    {
        $operacao = $type === InventoryMovement::TYPE_OUT ? 'saída' : 'entrada';
        $total = $packages * $package->factor;

        $texto = "{$operacao} por embalagem: {$packages} × {$package->name} = {$total} {$product->unit}";

        return Str::limit($reason ? "{$texto} · {$reason}" : $texto, 252);
    }

    /**
     * Embalagens por produto, só de produtos UN, para o seletor do formulário.
     */
    private function packageOptions(): array
    {
        return ProductPackage::query()
            ->whereHas('product', fn ($query) => $query->active()->where('unit', 'UN'))
            ->orderBy('factor')
            ->get(['id', 'product_id', 'name', 'factor'])
            ->groupBy('product_id')
            ->map(fn ($packages) => $packages
                ->map(fn (ProductPackage $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'factor' => $package->factor,
                ])
                ->values()
                ->all())
            ->all();
    }

    private function statusOptions(): array
    {
        return [
            '' => 'todos',
            'available' => 'com disponível',
            'low' => 'baixo estoque',
            'over' => 'excesso',
        ];
    }

    private function summary(): array
    {
        $items = Inventory::query()->with('stockLevel')->get();

        return [
            'items' => $items->count(),
            'on_hand' => number_format($items->sum(fn (Inventory $item) => (float) $item->quantity_on_hand), 3, ',', '.'),
            'available' => number_format($items->sum(fn (Inventory $item) => (float) $item->available), 3, ',', '.'),
            'low' => $items->filter(fn (Inventory $item) => $item->stockLevel && (float) $item->quantity_on_hand <= (float) $item->stockLevel->reorder_point)->count(),
            'over' => $items->filter(fn (Inventory $item) => $item->stockLevel?->max_qty !== null && (float) $item->quantity_on_hand > (float) $item->stockLevel->max_qty)->count(),
        ];
    }
}
