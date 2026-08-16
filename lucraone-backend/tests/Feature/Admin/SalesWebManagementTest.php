<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F2.3 — Sales & Orders (painel administrativo)
 */
class SalesWebManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private Company $company;

    private Product $product;

    private User $admin;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        $this->company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create([
            'trade_name' => 'Casa Alta',
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'sku' => 'VEN-100',
            'name' => 'Vinho Tinto',
        ]);
        Price::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_SALE,
            'currency' => 'BRL',
            'amount' => 40.00,
        ]);

        $this->admin = User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Ana Admin']);
        $this->adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantAtual->id)
            ->create(['id' => (string) Str::ulid()]);

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    public function test_listagem_de_pedidos_renderiza_apenas_tenant_atual_e_help(): void
    {
        $cliente = Customer::factory()->forCompany($this->company)->create(['name' => 'Bar do Zé']);
        Order::factory()->forCompany($this->company)->create([
            'customer_id' => $cliente->id,
            'order_number' => 'PED-202608-0001',
        ]);

        $outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        Order::factory()->forCompany($outraCompany)->create(['order_number' => 'PED-202608-9999']);

        $this->actingAs($this->admin)
            ->get(route('sales.orders.index'))
            ->assertOk()
            ->assertSee('vendas')
            ->assertSee('ajuda de vendas')
            ->assertSee('PED-202608-0001')
            ->assertSee('Bar do Zé')
            ->assertDontSee('PED-202608-9999');
    }

    public function test_criar_pedido_pela_tela_cadastra_cliente_inline(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('sales.orders.store'), [
            'company_id' => (string) $this->company->id,
            'customer_name' => 'Marina Nova',
            'customer_email' => 'marina@teste.test',
            'notes' => 'entregar na sexta',
        ]);

        $order = Order::withoutGlobalScopes()->where('tenant_id', $this->tenantAtual->id)->firstOrFail();

        $resposta->assertRedirect(route('sales.orders.show', $order));

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenantAtual->id,
            'name' => 'Marina Nova',
            'email' => 'marina@teste.test',
        ]);
        $this->assertSame(Order::STATUS_DRAFT, $order->status);
        $this->assertSame($order->customer_id, Customer::withoutGlobalScopes()->firstOrFail()->id);
    }

    public function test_adicionar_item_usa_preco_de_venda_e_recalcula_total(): void
    {
        $order = Order::factory()->forCompany($this->company)->create();

        $this->actingAs($this->admin)
            ->post(route('sales.orders.items.store', $order), [
                'product_id' => (string) $this->product->id,
                'quantity' => '2',
            ])
            ->assertRedirect(route('sales.orders.show', $order));

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => '2.000',
            'unit_price' => '40.00',
            'total' => '80.00',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'subtotal' => '80.00',
            'total' => '80.00',
        ]);
    }

    public function test_remover_item_recalcula_total(): void
    {
        $order = Order::factory()->forCompany($this->company)->create();
        $item = OrderItem::factory()->forOrder($order, $this->product)->create([
            'quantity' => 2,
            'unit_price' => 40,
            'total' => 80,
        ]);
        $order->recalculateTotals();

        $this->actingAs($this->admin)
            ->delete(route('sales.orders.items.destroy', [$order, $item]))
            ->assertRedirect(route('sales.orders.show', $order));

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total' => '0.00']);
    }

    public function test_atualizar_status_pela_tela_reserva_estoque(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 10,
            'reserved' => 0,
        ]);

        $order = Order::factory()->forCompany($this->company)->status(Order::STATUS_PENDING)->create();
        OrderItem::factory()->forOrder($order, $this->product)->create([
            'quantity' => 3,
            'unit_price' => 40,
            'total' => 120,
        ]);

        $this->actingAs($this->admin)
            ->put(route('sales.orders.status', $order), ['status' => Order::STATUS_CONFIRMED])
            ->assertRedirect(route('sales.orders.show', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_CONFIRMED]);
        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity_on_hand' => '10.000',
            'reserved' => '3.000',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => InventoryMovement::TYPE_RESERVATION,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_cancelar_pedido_pela_tela_libera_reserva(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 10,
            'reserved' => 3,
        ]);

        $order = Order::factory()->forCompany($this->company)->status(Order::STATUS_CONFIRMED)->create();
        OrderItem::factory()->forOrder($order, $this->product)->create([
            'quantity' => 3,
            'unit_price' => 40,
            'total' => 120,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('sales.orders.destroy', $order))
            ->assertRedirect(route('sales.orders.show', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => Order::STATUS_CANCELLED]);
        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'reserved' => '0.000',
        ]);
    }

    public function test_detalhe_do_pedido_mostra_itens_e_totais(): void
    {
        $cliente = Customer::factory()->forCompany($this->company)->create(['name' => 'Bar do Zé']);
        $order = Order::factory()->forCompany($this->company)->create(['customer_id' => $cliente->id]);
        OrderItem::factory()->forOrder($order, $this->product)->create([
            'quantity' => 2,
            'unit_price' => 40,
            'total' => 80,
        ]);
        $order->recalculateTotals();

        $this->actingAs($this->admin)
            ->get(route('sales.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Vinho Tinto')
            ->assertSee('Bar do Zé')
            ->assertSee('80,00');
    }

    public function test_cliente_pode_ser_criado_e_editado_pela_tela(): void
    {
        $this->actingAs($this->admin)
            ->post(route('sales.customers.store'), [
                'company_id' => (string) $this->company->id,
                'name' => 'Restaurante Sol',
                'email' => 'sol@teste.test',
                'status' => Customer::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::withoutGlobalScopes()->where('name', 'Restaurante Sol')->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('sales.customers.update', $customer), [
                'company_id' => (string) $this->company->id,
                'name' => 'Restaurante Sol Nascente',
                'status' => Customer::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('sales.customers.show', $customer));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Restaurante Sol Nascente',
            'status' => Customer::STATUS_INACTIVE,
        ]);
    }

    public function test_listagem_de_clientes_isola_por_tenant(): void
    {
        Customer::factory()->forCompany($this->company)->create(['name' => 'Cliente Daqui']);

        $outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        Customer::factory()->forCompany($outraCompany)->create(['name' => 'Cliente De Fora']);

        $this->actingAs($this->admin)
            ->get(route('sales.customers.index'))
            ->assertOk()
            ->assertSee('ajuda de clientes')
            ->assertSee('Cliente Daqui')
            ->assertDontSee('Cliente De Fora');
    }

    public function test_usuario_sem_permissao_recebe_403_em_vendas_e_clientes(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)->get(route('sales.orders.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('sales.customers.index'))->assertForbidden();
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        foreach (['manage-sales', 'view-sales', 'manage-customers', 'view-customers'] as $permissionName) {
            $permission = Permission::factory()
                ->forTenant($tenant->id)
                ->create([
                    'name' => $permissionName,
                    'description' => $permissionName,
                ]);

            $this->adminRole->grantPermission($permission);
        }

        $user->assignRole($this->adminRole, $tenant->id);
    }
}
