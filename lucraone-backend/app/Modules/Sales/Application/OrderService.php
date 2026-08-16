<?php

namespace App\Modules\Sales\Application;

use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras de negócio de pedidos.
 *
 * O serviço concentra o fluxo de status e a conversa com o estoque para que
 * API e painel web se comportem exatamente igual. Ver ADR-004.
 */
class OrderService
{
    public function __construct(
        private TenantContext $context,
        private OrderNumberGenerator $numbers,
        private InventoryAdjustmentService $inventory
    ) {}

    public function create(array $data, ?string $userId = null): Order
    {
        return DB::transaction(function () use ($data, $userId) {
            $customer = $this->resolveCustomer($data);

            return Order::create([
                'tenant_id' => $this->context->id(),
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'customer_id' => $customer?->id,
                'user_id' => $userId,
                'order_number' => $this->numbers->next(),
                'status' => Order::STATUS_DRAFT,
                'discount' => $data['discount'] ?? 0,
                'currency' => $data['currency'] ?? 'BRL',
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Cria o pedido junto com seus itens: tudo ou nada.
     *
     * @param  array  $data  precisa trazer `items` como lista de
     *                       `product_id`, `quantity` e `unit_price` opcional
     *
     * @throws InvalidArgumentException quando um item não pode ser precificado
     *                                  ou não pertence à empresa do pedido
     */
    public function createWithItems(array $data, ?string $userId = null): Order
    {
        return DB::transaction(function () use ($data, $userId) {
            $order = $this->create($data, $userId);

            foreach ($data['items'] ?? [] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);

                $this->addItem(
                    $order,
                    $product,
                    (float) $item['quantity'],
                    isset($item['unit_price']) ? (float) $item['unit_price'] : null
                );
            }

            return $order->fresh(['customer', 'items']);
        });
    }

    /**
     * Adiciona (ou soma) um item ao pedido.
     *
     * Sem preço informado, busca o preço de venda cadastrado no módulo de
     * produtos — é a "automatic pricing from price table" da sprint.
     *
     * @throws InvalidArgumentException
     */
    public function addItem(Order $order, Product $product, float $quantity, ?float $unitPrice = null): OrderItem
    {
        $this->ensureEditable($order);

        if ($quantity <= 0) {
            throw new InvalidArgumentException('a quantidade precisa ser maior que zero.');
        }

        if ($product->company_id !== $order->company_id) {
            throw new InvalidArgumentException('o produto não pertence à empresa do pedido.');
        }

        $preco = $unitPrice ?? $this->salePrice($product, $order->currency);

        if ($preco === null) {
            throw new InvalidArgumentException('produto sem preço de venda cadastrado; informe o valor unitário.');
        }

        return DB::transaction(function () use ($order, $product, $quantity, $preco) {
            $item = $order->items()->where('product_id', $product->id)->first();

            $novaQuantidade = $quantity + (float) ($item?->quantity ?? 0);

            $item = OrderItem::updateOrCreate(
                [
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                ],
                [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $novaQuantidade,
                    'unit_price' => $preco,
                    'total' => round($novaQuantidade * $preco, 2),
                ]
            );

            $order->recalculateTotals();

            return $item;
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function removeItem(Order $order, OrderItem $item): void
    {
        $this->ensureEditable($order);

        if ($item->order_id !== $order->id) {
            throw new InvalidArgumentException('o item não pertence a este pedido.');
        }

        DB::transaction(function () use ($order, $item) {
            $item->delete();
            $order->recalculateTotals();
        });
    }

    /**
     * Move o pedido no fluxo, aplicando os efeitos de estoque da transição.
     *
     * @throws InvalidArgumentException em transição inválida, pedido sem itens
     *                                  ou saldo insuficiente para reservar
     */
    public function changeStatus(Order $order, string $status, ?string $userId = null): Order
    {
        if ($status === $order->status) {
            return $order;
        }

        if (! $order->canTransitionTo($status)) {
            throw new InvalidArgumentException("não é possível mudar de {$order->status} para {$status}.");
        }

        if ($status === Order::STATUS_CONFIRMED && $order->items()->count() === 0) {
            throw new InvalidArgumentException('não é possível confirmar um pedido sem itens.');
        }

        return DB::transaction(function () use ($order, $status, $userId) {
            $anterior = $order->status;

            match (true) {
                $status === Order::STATUS_CONFIRMED => $this->reserveStock($order, $userId),
                $status === Order::STATUS_SHIPPED => $this->shipStock($order, $userId),
                $status === Order::STATUS_CANCELLED && $anterior === Order::STATUS_CONFIRMED => $this->releaseStock($order, $userId, 'cancelamento do pedido'),
                default => null,
            };

            $order->forceFill([
                'status' => $status,
                ...$this->timestampFor($status),
            ])->save();

            return $order->fresh(['items', 'customer', 'company']);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function cancel(Order $order, ?string $userId = null): Order
    {
        return $this->changeStatus($order, Order::STATUS_CANCELLED, $userId);
    }

    /**
     * Confirmação reserva o saldo: sai do disponível sem sair do saldo físico.
     */
    private function reserveStock(Order $order, ?string $userId): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            $this->moveStock($order, $item, InventoryMovement::TYPE_RESERVATION, $userId, "reserva do pedido {$order->order_number}");
        }
    }

    /**
     * Envio libera a reserva e dá a baixa definitiva no saldo físico.
     */
    private function shipStock(Order $order, ?string $userId): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            $this->moveStock($order, $item, InventoryMovement::TYPE_RELEASE, $userId, "baixa do pedido {$order->order_number}");
            $this->moveStock($order, $item, InventoryMovement::TYPE_OUT, $userId, "baixa do pedido {$order->order_number}");
        }
    }

    private function releaseStock(Order $order, ?string $userId, string $motivo): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            $this->moveStock($order, $item, InventoryMovement::TYPE_RELEASE, $userId, $motivo);
        }
    }

    private function moveStock(Order $order, OrderItem $item, string $tipo, ?string $userId, string $motivo): void
    {
        if (! $item->product) {
            throw new InvalidArgumentException("produto do item {$item->name} não está mais disponível.");
        }

        try {
            $this->inventory->adjust(
                $item->product,
                $order->company_id,
                $tipo,
                (float) $item->quantity,
                $motivo,
                $userId
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException("{$item->name}: {$exception->getMessage()}");
        }
    }

    private function timestampFor(string $status): array
    {
        return match ($status) {
            Order::STATUS_CONFIRMED => ['confirmed_at' => now()],
            Order::STATUS_SHIPPED => ['shipped_at' => now()],
            Order::STATUS_COMPLETED => ['completed_at' => now()],
            Order::STATUS_CANCELLED => ['cancelled_at' => now()],
            default => [],
        };
    }

    private function ensureEditable(Order $order): void
    {
        if (! $order->isEditable()) {
            throw new InvalidArgumentException('só é possível alterar itens de pedidos em rascunho ou aguardando.');
        }
    }

    private function salePrice(Product $product, string $currency): ?float
    {
        $price = $product->prices()
            ->sale()
            ->where('currency', $currency)
            ->first()
            ?? $product->prices()->sale()->first();

        return $price ? (float) $price->amount : null;
    }

    /**
     * Cliente pode vir pelo id ou ser criado na hora, direto do formulário
     * de pedido (criação inline exigida pela sprint).
     */
    private function resolveCustomer(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->findOrFail($data['customer_id']);
        }

        if (empty($data['customer_name'])) {
            return null;
        }

        return Customer::create([
            'tenant_id' => $this->context->id(),
            'company_id' => $data['company_id'],
            'name' => $data['customer_name'],
            'email' => $data['customer_email'] ?? null,
            'phone' => $data['customer_phone'] ?? null,
            'status' => Customer::STATUS_ACTIVE,
        ]);
    }
}
