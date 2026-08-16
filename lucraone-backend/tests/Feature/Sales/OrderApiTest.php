<?php

namespace Tests\Feature\Sales;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2.3 — Sales & Orders (API)
 */
class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private Company $company;

    private Product $product;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->outroTenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => 'VEN-001',
            'name' => 'Café Torrado',
        ]);
        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_SALE,
            'currency' => 'BRL',
            'amount' => 25.00,
        ]);
        $this->user = User::factory()->forTenant($this->tenant)->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_order_creation_uses_price_table_and_generates_number(): void
    {
        $resposta = $this->requisicao()
            ->postJson('/api/v1/orders', [
                'company_id' => $this->company->id,
                'customer_name' => 'Marina Compradora',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Order::STATUS_DRAFT)
            ->assertJsonPath('data.items.0.unit_price', '25.00')
            ->assertJsonPath('data.items.0.total', '75.00')
            ->assertJsonPath('data.total', '75.00');

        $this->assertStringStartsWith('PED-'.now()->format('Ym').'-', $resposta->json('data.order_number'));

        // Cliente criado inline durante o pedido
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Marina Compradora',
        ]);
    }

    public function test_explicit_unit_price_overrides_price_table(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/orders', [
                'company_id' => $this->company->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 19.90],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '19.90')
            ->assertJsonPath('data.total', '39.80');
    }

    public function test_product_without_sale_price_is_rejected(): void
    {
        $semPreco = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Produto Sem Preço',
        ]);

        $this->requisicao()
            ->postJson('/api/v1/orders', [
                'company_id' => $this->company->id,
                'items' => [
                    ['product_id' => $semPreco->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'produto sem preço de venda cadastrado; informe o valor unitário.');
    }

    public function test_confirming_order_reserves_inventory(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 10,
            'reserved' => 0,
        ]);

        $order = $this->criarPedido(4);

        $this->mudarStatus($order, Order::STATUS_PENDING)->assertOk();
        $this->mudarStatus($order, Order::STATUS_CONFIRMED)
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CONFIRMED);

        // Reserva sai do disponível mas não do saldo físico
        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity_on_hand' => '10.000',
            'reserved' => '4.000',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => InventoryMovement::TYPE_RESERVATION,
            'quantity' => '4.000',
        ]);
    }

    public function test_shipping_order_converts_reservation_into_stock_out(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 10,
            'reserved' => 0,
        ]);

        $order = $this->criarPedido(4);

        $this->mudarStatus($order, Order::STATUS_PENDING)->assertOk();
        $this->mudarStatus($order, Order::STATUS_CONFIRMED)->assertOk();
        $this->mudarStatus($order, Order::STATUS_SHIPPED)->assertOk();

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity_on_hand' => '6.000',
            'reserved' => '0.000',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => '4.000',
        ]);
    }

    public function test_concurrent_orders_cannot_oversell(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 5,
            'reserved' => 0,
        ]);

        $primeiro = $this->criarPedido(4);
        $segundo = $this->criarPedido(4);

        $this->mudarStatus($primeiro, Order::STATUS_PENDING)->assertOk();
        $this->mudarStatus($primeiro, Order::STATUS_CONFIRMED)->assertOk();

        $this->mudarStatus($segundo, Order::STATUS_PENDING)->assertOk();

        // O segundo pedido não pode reservar o que já está comprometido
        $this->mudarStatus($segundo, Order::STATUS_CONFIRMED)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Café Torrado: reserva maior que o saldo disponível.');

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity_on_hand' => '5.000',
            'reserved' => '4.000',
        ]);
        // A transição falhou inteira: o pedido continua onde estava
        $this->assertDatabaseHas('orders', [
            'id' => $segundo['id'],
            'status' => Order::STATUS_PENDING,
        ]);
    }

    public function test_cancelling_confirmed_order_releases_reservation(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 10,
            'reserved' => 0,
        ]);

        $order = $this->criarPedido(3);

        $this->mudarStatus($order, Order::STATUS_PENDING)->assertOk();
        $this->mudarStatus($order, Order::STATUS_CONFIRMED)->assertOk();

        $this->requisicao()
            ->deleteJson("/api/v1/orders/{$order['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity_on_hand' => '10.000',
            'reserved' => '0.000',
        ]);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $order = $this->criarPedido(1);

        $this->requisicao()
            ->putJson("/api/v1/orders/{$order['id']}/status", ['status' => Order::STATUS_SHIPPED])
            ->assertStatus(422)
            ->assertJsonPath('message', 'não é possível mudar de draft para shipped.');
    }

    public function test_order_without_items_cannot_be_confirmed(): void
    {
        $order = $this->requisicao()
            ->postJson('/api/v1/orders', ['company_id' => $this->company->id, 'customer_name' => 'Sem Itens'])
            ->assertCreated()
            ->json('data');

        $this->mudarStatus($order, Order::STATUS_PENDING)->assertOk();

        $this->mudarStatus($order, Order::STATUS_CONFIRMED)
            ->assertStatus(422)
            ->assertJsonPath('message', 'não é possível confirmar um pedido sem itens.');
    }

    public function test_orders_are_isolated_by_tenant(): void
    {
        $meu = $this->criarPedido(1);

        $outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        $outroCliente = Customer::factory()->forCompany($outraCompany)->create();
        Order::factory()->forCompany($outraCompany)->create(['customer_id' => $outroCliente->id]);

        $ids = collect(
            $this->requisicao()->getJson('/api/v1/orders')->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($meu['id']));
        $this->assertCount(1, $ids);
    }

    public function test_orders_require_authentication(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    private function requisicao()
    {
        return $this->withToken($this->token)->withHeader('X-Tenant-ID', $this->tenant->id);
    }

    private function mudarStatus(array $order, string $status)
    {
        return $this->requisicao()->putJson("/api/v1/orders/{$order['id']}/status", ['status' => $status]);
    }

    private function criarPedido(float $quantidade): array
    {
        return $this->requisicao()
            ->postJson('/api/v1/orders', [
                'company_id' => $this->company->id,
                'customer_name' => 'Cliente Teste',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => $quantidade],
                ],
            ])
            ->assertCreated()
            ->json('data');
    }
}
