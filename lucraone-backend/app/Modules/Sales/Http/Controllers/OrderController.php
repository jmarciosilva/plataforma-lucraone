<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Application\OrderService;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Http\Requests\StoreOrderRequest;
use App\Modules\Sales\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Sales\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

/**
 * As queries de Order já vêm filtradas pelo tenant atual via TenantScope,
 * então o controller não precisa repetir o filtro.
 */
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with(['customer', 'items'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('company_id'), fn ($query) => $query->where('company_id', $request->string('company_id')->toString()))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->string('customer_id')->toString()))
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orders->createWithItems($request->validated(), $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): OrderResource
    {
        $order = Order::query()
            ->with(['customer', 'items', 'company'])
            ->findOrFail($id);

        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): OrderResource|JsonResponse
    {
        $order = Order::query()->findOrFail($id);

        try {
            $order = $this->orders->changeStatus($order, $request->validated('status'), $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new OrderResource($order->load(['customer', 'items']));
    }

    public function destroy(Request $request, string $id): OrderResource|JsonResponse
    {
        $order = Order::query()->findOrFail($id);

        try {
            $order = $this->orders->cancel($order, $request->user()?->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new OrderResource($order->load(['customer', 'items']));
    }
}
