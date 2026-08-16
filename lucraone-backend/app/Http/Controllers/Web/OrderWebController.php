<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebOrderItemRequest;
use App\Http\Requests\StoreWebOrderRequest;
use App\Http\Requests\UpdateWebOrderStatusRequest;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Application\OrderService;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class OrderWebController extends Controller
{
    public const STATUS_ROTULOS = [
        Order::STATUS_DRAFT => 'rascunho',
        Order::STATUS_PENDING => 'aguardando',
        Order::STATUS_CONFIRMED => 'confirmado',
        Order::STATUS_SHIPPED => 'enviado',
        Order::STATUS_COMPLETED => 'concluído',
        Order::STATUS_CANCELLED => 'cancelado',
    ];

    public function __construct(
        private OrderService $orders
    ) {}

    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Order::class);

        $orders = Order::query()
            ->with(['customer', 'company'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->string('company_id')->toString()))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->string('customer_id')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'companies' => $this->companies(),
            'customers' => $this->customers(),
            'statusRotulos' => self::STATUS_ROTULOS,
            'summary' => $this->summary(),
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', Order::class);

        return view('orders.create', [
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'novo']],
            'companies' => $this->companies(),
            'customers' => $this->customers(),
        ]);
    }

    public function store(StoreWebOrderRequest $request)
    {
        try {
            $order = $this->orders->create($request->validated(), $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['customer_id' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('sales.orders.show', $order)
            ->with('sucesso', 'pedido criado. adicione os itens abaixo.');
    }

    public function show(Order $order, TenantContext $context)
    {
        Gate::authorize('view', $order);

        $order->load(['customer', 'company', 'user', 'items.product']);

        return view('orders.show', [
            'order' => $order,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $order->order_number]],
            'statusRotulos' => self::STATUS_ROTULOS,
            'proximosStatus' => $this->proximosStatus($order),
            'products' => $this->products($order->company_id),
        ]);
    }

    public function storeItem(StoreWebOrderItemRequest $request, Order $order)
    {
        $product = Product::query()->findOrFail($request->validated('product_id'));

        try {
            $this->orders->addItem(
                $order,
                $product,
                (float) $request->validated('quantity'),
                $request->filled('unit_price') ? (float) $request->validated('unit_price') : null
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['product_id' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('sales.orders.show', $order)
            ->with('sucesso', 'item adicionado ao pedido.');
    }

    public function destroyItem(Order $order, OrderItem $item)
    {
        Gate::authorize('update', $order);

        try {
            $this->orders->removeItem($order, $item);
        } catch (InvalidArgumentException $exception) {
            return back()->with('erro', $exception->getMessage());
        }

        return redirect()
            ->route('sales.orders.show', $order)
            ->with('sucesso', 'item removido do pedido.');
    }

    public function updateStatus(UpdateWebOrderStatusRequest $request, Order $order)
    {
        try {
            $this->orders->changeStatus($order, $request->validated('status'), $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return back()->with('erro', $exception->getMessage());
        }

        return redirect()
            ->route('sales.orders.show', $order)
            ->with('sucesso', 'status do pedido atualizado.');
    }

    public function destroy(Request $request, Order $order)
    {
        Gate::authorize('delete', $order);

        try {
            $this->orders->cancel($order, $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return back()->with('erro', $exception->getMessage());
        }

        return redirect()
            ->route('sales.orders.show', $order)
            ->with('sucesso', 'pedido cancelado e estoque liberado.');
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'vendas', 'url' => route('sales.orders.index')],
        ];
    }

    /**
     * Só oferece na tela as transições que o fluxo aceita a partir de agora.
     */
    private function proximosStatus(Order $order): array
    {
        return collect(Order::TRANSICOES[$order->status] ?? [])
            ->mapWithKeys(fn (string $status) => [$status => self::STATUS_ROTULOS[$status] ?? $status])
            ->all();
    }

    private function companies()
    {
        return Company::query()
            ->where('status', 'ACTIVE')
            ->orderBy('trade_name')
            ->orderBy('legal_name')
            ->get();
    }

    private function customers()
    {
        return Customer::query()->active()->orderBy('name')->get();
    }

    private function products(string $companyId)
    {
        return Product::query()
            ->with('prices')
            ->active()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    private function summary(): array
    {
        $orders = Order::query()->get();

        return [
            'total' => $orders->count(),
            'abertos' => $orders->whereNotIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED])->count(),
            'confirmados' => $orders->where('status', Order::STATUS_CONFIRMED)->count(),
            'faturado' => number_format(
                Order::query()->revenue()->sum('total'),
                2,
                ',',
                '.'
            ),
        ];
    }
}
