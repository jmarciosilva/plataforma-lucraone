<?php

namespace App\Modules\Sales\Application;

use App\Modules\Automation\Domain\Events\AutomationTriggered;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Products\Domain\Services\ProductQuantity;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
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

                $price = isset($item['unit_price']) ? (float) $item['unit_price'] : null;
                if (! empty($item['package_id'])) {
                    $package = ProductPackage::query()->findOrFail($item['package_id']);
                    $this->addPackageItem($order, $product, $package, $item['quantity'], $price);
                } else {
                    $this->addItem($order, $product, $item['quantity'], $price);
                }
            }

            return $order->fresh(['customer', 'items']);
        });
    }

    /** Adiciona quantidade na unidade base; preserva chamadas existentes. */
    public function addItem(Order $order, Product $product, mixed $quantity, ?float $unitPrice = null): OrderItem
    {
        return $this->addPresentation($order, $product, $quantity, $unitPrice, null);
    }

    /** Quantidade é número de embalagens; preço continua por unidade base. */
    public function addPackageItem(Order $order, Product $product, ProductPackage $package, mixed $quantity, ?float $unitPrice = null): OrderItem
    {
        return $this->addPresentation($order, $product, $quantity, $unitPrice, $package);
    }

    private function addPresentation(Order $order, Product $product, mixed $quantity, ?float $unitPrice, ?ProductPackage $package): OrderItem
    {
        return DB::transaction(function () use ($order, $product, $quantity, $unitPrice, $package) {
            // Todas as mutações seguem Order → OrderItem → Inventory.
            $this->lockOrder($order);
            $this->ensureEditable($order);
            $product = Product::query()->find($product->id);
            if (! $product || $product->tenant_id !== $order->tenant_id || $product->company_id !== $order->company_id) {
                throw new InvalidArgumentException('o produto não pertence à empresa do pedido.');
            }

            $items = $order->items()->where('product_id', $product->id)->lockForUpdate()->get();
            foreach ($items as $existing) {
                $this->ensureHistoricalUnit($existing, $product);
            }

            $presentation = [
                'sale_presentation_type' => 'base',
                'product_package_id' => null,
                'package_name' => null,
                'package_factor' => null,
                'presentation_barcode' => $product->barcode,
            ];

            if ($package) {
                $package = ProductPackage::query()->find($package->id);
                if (! $package || $package->tenant_id !== $order->tenant_id || (string) $package->product_id !== (string) $product->id
                    || $product->status !== 'active' || $package->factor < 2 || $package->factor > 100000) {
                    throw new InvalidArgumentException('Esta embalagem não pode ser utilizada para este produto.');
                }
                if ($product->unit !== 'UN') {
                    throw new InvalidArgumentException('Esta embalagem não pode ser utilizada porque o produto não é vendido por unidade.');
                }
                // Nenhum preço comercial da embalagem é inferido da tabela SALE.
                if ($unitPrice === null) {
                    throw new InvalidArgumentException('Informe o valor por unidade base para vender esta embalagem.');
                }
                $packages = ProductQuantity::normalize('UN', $quantity);
                $quantity = (string) BigDecimal::of($packages)->multipliedBy($package->factor);
                $presentation = [
                    'sale_presentation_type' => 'package',
                    'product_package_id' => (string) $package->id,
                    'package_name' => $package->name,
                    'package_factor' => $package->factor,
                    'presentation_barcode' => $package->barcode,
                ];
            }

            $quantity = ProductQuantity::normalize($product->unit, $quantity);
            $price = $unitPrice ?? $this->salePrice($product, $order->currency);
            if ($price === null) {
                throw new InvalidArgumentException('produto sem preço de venda cadastrado; informe o valor unitário.');
            }
            if (! is_finite($price) || $price < 0 || $price >= 1000000000000) {
                throw new InvalidArgumentException('Informe um valor por unidade base com até duas casas decimais.');
            }
            try {
                $price = (string) BigDecimal::of((string) $price)->toScale(2, RoundingMode::Unnecessary);
            } catch (RoundingNecessaryException) {
                throw new InvalidArgumentException('Informe um valor por unidade base com até duas casas decimais.');
            }
            $identity = ['sku' => $product->sku, 'name' => $product->name, 'unit' => $product->unit, ...$presentation];
            $item = $items->first(function (OrderItem $candidate) use ($identity, $price) {
                if ($candidate->unit_price !== $price) {
                    return false;
                }
                foreach ($identity as $field => $value) {
                    if ($value !== $candidate->$field) {
                        return false;
                    }
                }

                return true;
            });
            $newQuantity = ProductQuantity::normalize($product->unit, (string) BigDecimal::of($quantity)->plus($item?->quantity ?? '0'));
            $values = ['quantity' => $newQuantity, 'unit_price' => $price, 'total' => round((float) $newQuantity * (float) $price, 2)];
            if ($item) {
                $item->update($values);
            } else {
                $item = OrderItem::create([
                    'tenant_id' => $order->tenant_id, 'order_id' => (string) $order->id,
                    'product_id' => (string) $product->id, ...$identity, ...$values,
                ]);
            }
            $order->recalculateTotals();

            return $item;
        });
    }

    private function lockOrder(Order $order): void
    {
        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
        $order->setRawAttributes($locked->getAttributes(), true);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function removeItem(Order $order, OrderItem $item): void
    {
        DB::transaction(function () use ($order, $item) {
            $this->lockOrder($order);
            $this->ensureEditable($order);
            $current = $order->items()->whereKey($item->id)->lockForUpdate()->first();
            if (! $current) {
                throw new InvalidArgumentException('o item não pertence a este pedido.');
            }
            $current->delete();
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
        return DB::transaction(function () use ($order, $status, $userId) {
            $this->lockOrder($order);
            if ($status === $order->status) {
                return $order;
            }
            if (! $order->canTransitionTo($status)) {
                throw new InvalidArgumentException("não é possível mudar de {$order->status} para {$status}.");
            }
            if ($status === Order::STATUS_CONFIRMED && $order->items()->count() === 0) {
                throw new InvalidArgumentException('não é possível confirmar um pedido sem itens.');
            }
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

            $atualizado = $order->fresh(['items', 'customer', 'company']);

            if ($status === Order::STATUS_COMPLETED) {
                $this->anunciarPedidoConcluido($atualizado);
            }

            return $atualizado;
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function cancel(Order $order, ?string $userId = null): Order
    {
        return $this->changeStatus($order, Order::STATUS_CANCELLED, $userId);
    }

    private function anunciarPedidoConcluido(Order $order): void
    {
        AutomationTriggered::dispatch($order->tenant_id, TriggerCatalog::PEDIDO_CONCLUIDO, [
            'order_id' => $order->id,
            'numero' => $order->order_number,
            'total' => (float) $order->total,
            'itens' => $order->items->count(),
            'cliente' => $order->customer?->name ?? 'sem cliente',
            'company_id' => $order->company_id,
        ]);
    }

    /**
     * Confirmação reserva o saldo: sai do disponível sem sair do saldo físico.
     */
    private function reserveStock(Order $order, ?string $userId): void
    {
        foreach ($this->stockItems($order) as $item) {
            $this->moveStock($order, $item, InventoryMovement::TYPE_RESERVATION, $userId, "reserva do pedido {$order->order_number}");
        }
    }

    /**
     * Envio libera a reserva e dá a baixa definitiva no saldo físico.
     */
    private function shipStock(Order $order, ?string $userId): void
    {
        foreach ($this->stockItems($order) as $item) {
            $this->moveStock($order, $item, InventoryMovement::TYPE_RELEASE, $userId, "baixa do pedido {$order->order_number}");
            $this->moveStock($order, $item, InventoryMovement::TYPE_OUT, $userId, "baixa do pedido {$order->order_number}");
        }
    }

    private function releaseStock(Order $order, ?string $userId, string $motivo): void
    {
        foreach ($this->stockItems($order) as $item) {
            $this->moveStock($order, $item, InventoryMovement::TYPE_RELEASE, $userId, $motivo);
        }
    }

    private function stockItems(Order $order): Collection
    {
        $items = $order->items()->with('product')->orderBy('product_id')->orderBy('id')->lockForUpdate()->get();

        // Valida todas as linhas antes da primeira movimentação de estoque.
        foreach ($items as $item) {
            if (! $item->product) {
                throw new InvalidArgumentException("produto do item {$item->name} não está mais disponível.");
            }

            $this->ensureHistoricalUnit($item, $item->product);
        }

        return $items;
    }

    private function moveStock(Order $order, OrderItem $item, string $tipo, ?string $userId, string $motivo): void
    {
        if (! $item->product) {
            throw new InvalidArgumentException("produto do item {$item->name} não está mais disponível.");
        }

        $this->ensureHistoricalUnit($item, $item->product);

        try {
            $this->inventory->adjust(
                $item->product,
                $order->company_id,
                $tipo,
                $item->quantity,
                $motivo,
                $userId
            );
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException("{$item->name}: {$exception->getMessage()}");
        }
    }

    private function ensureHistoricalUnit(OrderItem $item, Product $product): void
    {
        if ($item->unit === null) {
            throw new InvalidArgumentException('Não é possível alterar este item porque a unidade histórica não está disponível.');
        }

        if ($item->unit !== $product->unit) {
            throw new InvalidArgumentException('A unidade deste produto foi alterada após a criação do pedido. O item não pode ser modificado.');
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
