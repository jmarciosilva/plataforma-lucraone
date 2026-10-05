<?php

namespace Tests\Feature\Products;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Application\OrderService;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductQuantityFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => (string) $this->company->id,
            'unit' => 'UN',
        ]);
        $this->user = User::factory()->forTenant($this->tenant)->create();
        $role = Role::factory()->admin()->forTenant($this->tenant->id)->create();
        foreach (['view-sales', 'manage-sales', 'view-inventory', 'manage-inventory'] as $name) {
            $role->grantPermission(Permission::factory()->forTenant($this->tenant->id)->create(['name' => $name]));
        }
        $this->user->assignRole($role, $this->tenant->id);
        app(TenantContext::class)->set($this->tenant->id);
    }

    public static function invalidHttpQuantities(): array
    {
        return [
            ['UN', '1.5', 'Produtos vendidos por unidade devem usar quantidade inteira.'],
            ['KG', '1.2345', 'Produtos vendidos por peso aceitam até 3 casas decimais.'],
            ['UN', '0', 'A quantidade deve ser maior que zero.'],
            ['KG', '-0.001', 'A quantidade deve ser maior que zero.'],
            ['UN', '1e3', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['KG', '1E3', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['KG', '1e-2', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['UN', '2E+5', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['UN', '100000000000', 'A quantidade informada é maior que o limite permitido.'],
            ['KG', '100000000000', 'A quantidade informada é maior que o limite permitido.'],
        ];
    }

    #[DataProvider('invalidHttpQuantities')]
    public function test_web_and_api_reject_the_same_quantity(string $unit, string $quantity, string $message): void
    {
        $this->product->update(['unit' => $unit]);
        $order = Order::factory()->forCompany($this->company)->create(['status' => Order::STATUS_DRAFT]);
        $item = ['product_id' => (string) $this->product->id, 'quantity' => $quantity, 'unit_price' => '0'];
        $this->actingAs($this->user)->post(route('sales.orders.items.store', $order), $item)
            ->assertSessionHasErrors(['quantity' => $message]);
        $response = $this->api()->postJson('/api/v1/orders', ['company_id' => (string) $this->company->id, 'items' => [$item]])
            ->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity']);
        $this->assertSame($message, $response->json('errors')['items.0.quantity'][0]);
        $adjustment = ['company_id' => (string) $this->company->id, 'type' => 'in', 'quantity' => $quantity];
        $this->actingAs($this->user)->post(route('inventory.adjust'), ['product_id' => (string) $this->product->id, ...$adjustment])
            ->assertSessionHasErrors(['quantity' => $message]);
        $this->api()->postJson("/api/v1/inventory/{$this->product->id}/adjust", $adjustment)
            ->assertUnprocessable()->assertJsonPath('errors.quantity.0', $message);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventories', 0);
    }

    #[DataProvider('validHttpQuantities')]
    public function test_web_and_api_persist_valid_quantities(string $unit, string $quantity, string $expected): void
    {
        $this->product->update(['unit' => $unit]);
        $order = Order::factory()->forCompany($this->company)->create(['status' => Order::STATUS_DRAFT]);
        $item = ['product_id' => (string) $this->product->id, 'quantity' => $quantity, 'unit_price' => '0'];
        $this->actingAs($this->user)->post(route('sales.orders.items.store', $order), $item)
            ->assertSessionHasNoErrors()->assertRedirect(route('sales.orders.show', $order));
        $this->assertSame($expected, $order->items()->sole()->quantity);
        $this->api()->postJson('/api/v1/orders', ['company_id' => (string) $this->company->id, 'items' => [$item]])
            ->assertCreated()->assertJsonPath('data.items.0.quantity', $expected);
        $adjustment = ['company_id' => (string) $this->company->id, 'type' => 'adjustment', 'quantity' => $quantity];
        $this->actingAs($this->user)->post(route('inventory.adjust'), ['product_id' => (string) $this->product->id, ...$adjustment])
            ->assertSessionHasNoErrors();
        $this->assertSame($expected, Inventory::query()->sole()->quantity_on_hand);
        $this->api()->postJson("/api/v1/inventory/{$this->product->id}/adjust", $adjustment)
            ->assertOk()->assertJsonPath('data.quantity_on_hand', $expected);
    }

    public static function validHttpQuantities(): array
    {
        return [['UN', '2.000', '2.000'], ['KG', '0.001', '0.001'], ['KG', '2.375', '2.375']];
    }

    public function test_order_service_rejects_request_bypass_and_rolls_back_all_items(): void
    {
        try {
            app(OrderService::class)->createWithItems([
                'company_id' => (string) $this->company->id,
                'customer_name' => 'Cliente não deve persistir',
                'items' => [
                    ['product_id' => (string) $this->product->id, 'quantity' => '1', 'unit_price' => 0],
                    ['product_id' => (string) $this->product->id, 'quantity' => '1.5', 'unit_price' => 0],
                ],
            ]);
            $this->fail('O serviço aceitou uma fração para UN.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Produtos vendidos por unidade devem usar quantidade inteira.', $exception->getMessage());
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_order_item_addition_is_exact_and_rejects_accumulated_overflow(): void
    {
        $this->product->update(['unit' => 'KG']);
        $orders = app(OrderService::class);
        $order = $orders->create(['company_id' => (string) $this->company->id]);
        $orders->addItem($order, $this->product, '0.100', 0);
        $this->assertSame('0.300', $orders->addItem($order, $this->product, '0.200', 0)->quantity);
        $order->items()->update(['quantity' => '99999999999.999']);
        try {
            $orders->addItem($order, $this->product, '0.001', 0);
            $this->fail('A soma excedeu o limite permitido.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('A quantidade informada é maior que o limite permitido.', $exception->getMessage());
        }
        $this->assertSame('99999999999.999', $order->items()->sole()->quantity);
    }

    public function test_inventory_decimal_operations_are_exact(): void
    {
        $this->product->update(['unit' => 'KG']);
        $service = app(InventoryAdjustmentService::class);
        $move = fn ($type, $quantity) => $service->adjust($this->product, $this->company->id, $type, $quantity, null);
        $this->assertSame('0.100', $move('in', '0.100')->quantity_on_hand);
        $balance = $move('in', '0.200');
        $this->assertSame('0.300', $balance->quantity_on_hand);
        $this->assertSame('0.300', $balance->available);
        $this->assertSame('0.200', $move('out', '0.100')->quantity_on_hand);
        $this->assertSame('0.000', $move('out', '0.200')->quantity_on_hand);
        $move('in', '0.300');
        try {
            $move('out', '0.301');
            $this->fail('Saída superior ao saldo foi aceita.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('saída maior que o saldo em mãos.', $exception->getMessage());
        }
        $this->assertSame('0.300', $balance->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 5);
    }

    public function test_reservation_release_and_available_are_exact(): void
    {
        $this->product->update(['unit' => 'KG']);
        $service = app(InventoryAdjustmentService::class);
        $move = fn ($type, $quantity) => $service->adjust($this->product, $this->company->id, $type, $quantity, null);
        $move('in', '0.300');
        $this->assertSame('0.200', $move('reservation', '0.100')->available);
        $this->assertSame('0.000', $move('reservation', '0.200')->available);
        $move('release', '0.100');
        $balance = $move('release', '0.200');
        $this->assertSame('0.000', $balance->reserved);
        $this->assertSame('0.300', $balance->available);
    }

    public function test_inventory_rejects_input_and_accumulated_overflow_without_movements(): void
    {
        $service = app(InventoryAdjustmentService::class);
        foreach (['1.5', '100000000000', '1e3'] as $quantity) {
            try {
                $service->adjust($this->product, $this->company->id, 'in', $quantity, null);
                $this->fail('Quantidade inválida aceita pelo serviço.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('inventories', 0);
                $this->assertDatabaseCount('inventory_movements', 0);
            }
        }
        $balance = $service->adjust($this->product, $this->company->id, 'in', '99999999999', null);
        try {
            $service->adjust($this->product, $this->company->id, 'in', '1', null);
            $this->fail('Saldo acima do limite foi aceito.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('A quantidade informada é maior que o limite permitido.', $exception->getMessage());
        }
        $this->assertSame('99999999999.000', $balance->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    #[DataProvider('rawJsonQuantities')]
    public function test_api_preserves_original_json_quantity_format(string $quantity, string $message): void
    {
        $this->product->update(['unit' => 'KG']);
        $item = json_encode(['product_id' => (string) $this->product->id, 'unit_price' => 0]);
        $item = substr($item, 0, -1).',"quantity":'.$quantity.'}';
        $body = '{"company_id":"'.$this->company->id.'","items":['.$item.']}';
        $response = $this->actingAs($this->user, 'sanctum')->call('POST', '/api/v1/orders', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => (string) $this->tenant->id,
        ], $body)->assertUnprocessable()->assertJsonValidationErrors(['items.0.quantity']);
        $this->assertSame($message, $response->json('errors')['items.0.quantity'][0]);
        $this->assertDatabaseCount('orders', 0);
    }

    public static function rawJsonQuantities(): array
    {
        return [
            ['1e3', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['1E3', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['1e-2', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['2E+5', 'Informe a quantidade usando números decimais, sem notação científica.'],
            ['1.0000', 'Produtos vendidos por peso aceitam até 3 casas decimais.'],
            ['1.00000000000000001', 'Produtos vendidos por peso aceitam até 3 casas decimais.'],
        ];
    }

    #[DataProvider('quantityInputUnits')]
    public function test_quantity_inputs_render_minimum_and_step_for_selected_product(string $unit, string $step): void
    {
        $this->product->update(['unit' => $unit]);
        $order = Order::factory()->forCompany($this->company)->create(['status' => Order::STATUS_DRAFT]);
        foreach ([route('sales.orders.show', $order), route('inventory.index')] as $url) {
            $response = $this->actingAs($this->user)
                ->withSession(['_old_input' => ['product_id' => (string) $this->product->id]])
                ->get($url)->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $input = $xpath->query('//input[@name="quantity"]')->item(0);
            $this->assertNotNull($input);
            $this->assertSame($step, $input->getAttribute('min'));
            $this->assertSame($step, $input->getAttribute('step'));
        }
    }

    public static function quantityInputUnits(): array
    {
        return [['UN', '1'], ['KG', '0.001']];
    }

    public function test_web_reports_accumulated_quantity_overflow_on_quantity_field(): void
    {
        $order = app(OrderService::class)->create(['company_id' => (string) $this->company->id]);
        app(OrderService::class)->addItem($order, $this->product, '99999999999', 0);
        $this->actingAs($this->user)->post(route('sales.orders.items.store', $order), [
            'product_id' => (string) $this->product->id,
            'quantity' => '1',
            'unit_price' => '0',
        ])->assertSessionHasErrors(['quantity' => 'A quantidade informada é maior que o limite permitido.']);
        $this->assertSame('99999999999.000', $order->items()->sole()->quantity);
    }

    public function test_raw_json_preserves_quantities_without_changing_other_normalized_fields(): void
    {
        $this->product->update(['unit' => 'KG']);
        $text = 'Texto contendo "quantity":1e3 e caracteres \\ escapados';
        $body = json_encode([
            'company_id' => (string) $this->company->id,
            'notes' => '  '.$text.'  ',
            'items' => [['product_id' => (string) $this->product->id, 'quantity' => '0.250', 'unit_price' => 0]],
        ]);
        $body = str_replace('"quantity":"0.250"', '"quantity":0.250', $body);
        $this->actingAs($this->user, 'sanctum')->call('POST', '/api/v1/orders', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => (string) $this->tenant->id,
        ], $body)->assertCreated()->assertJsonPath('data.items.0.quantity', '0.250');
        $this->assertSame($text, Order::query()->sole()->notes);
    }

    private function api(): static
    {
        return $this->withToken($this->user->createToken('pm-04a')->plainTextToken)
            ->withHeader('X-Tenant-ID', $this->tenant->id);
    }
}
