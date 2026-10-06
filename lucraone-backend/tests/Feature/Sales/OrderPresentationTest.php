<?php

namespace Tests\Feature\Sales;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Application\ProductBarcodeResolver;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Sales\Application\OrderService;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderPresentationTest extends TestCase
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

    private function package(array $attributes = []): ProductPackage
    {
        return ProductPackage::factory()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->product->id,
            'name' => 'Caixa 12', 'factor' => 12, 'barcode' => '7891234567890', ...$attributes,
        ]);
    }

    private function order(): Order
    {
        return app(OrderService::class)->create(['company_id' => (string) $this->company->id]);
    }

    public function test_base_and_weight_keep_base_contract(): void
    {
        $service = app(OrderService::class);
        foreach (['UN' => '3', 'KG' => '0.350'] as $unit => $quantity) {
            $this->product->update(['unit' => $unit]);
            $item = $service->addItem($this->order(), $this->product, $quantity, 2.5)->fresh();
            $this->assertSame('base', $item->sale_presentation_type);
            $this->assertSame($unit, $item->unit);
            $this->assertSame(number_format((float) $quantity, 3, '.', ''), $item->quantity);
            foreach (['product_package_id', 'package_name', 'package_factor'] as $field) {
                $this->assertNull($item->$field);
            }
        }
    }

    public function test_base_box_and_two_packages_coexist_and_accumulate(): void
    {
        $service = app(OrderService::class);
        $order = $this->order();
        $box = $this->package();
        $fardo = $this->package(['name' => 'Fardo 24', 'factor' => 24, 'barcode' => null]);
        $base = $service->addItem($order, $this->product, '3', 2.5);
        $item = $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $this->assertSame('12.000', $item->fresh()->quantity);
        $again = $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $this->assertSame((string) $item->id, (string) $again->id);
        $service->addPackageItem($order, $this->product, $fardo, '1', 2.5);
        $this->assertSame(3, $order->items()->count());
        $this->assertSame('24.000', $item->fresh()->quantity);
        $this->assertSame('3.000', $base->fresh()->quantity);
        $this->assertSame('Caixa 12', $item->fresh()->package_name);
        $this->assertSame(12, $item->fresh()->package_factor);
        $this->assertSame('127.50', $order->fresh()->total);
    }

    public function test_different_prices_never_reprice_existing_base_or_package(): void
    {
        $service = app(OrderService::class);
        $order = $this->order();
        $box = $this->package();
        $base = $service->addItem($order, $this->product, '2', 5);
        $service->addItem($order, $this->product, '1', 4.5);
        $package = $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $service->addPackageItem($order, $this->product, $box, '1', 2);
        $this->assertSame(4, $order->items()->count());
        $this->assertSame('5.00', $base->fresh()->unit_price);
        $this->assertSame('10.00', $base->fresh()->total);
        $this->assertSame('30.00', $package->fresh()->total);
        $this->assertSame('68.50', $order->fresh()->total);
    }

    public function test_manual_and_resolved_barcode_accumulate_same_presentation(): void
    {
        $box = $this->package();
        $order = $this->order();
        $service = app(OrderService::class);
        $item = $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $resolved = app(ProductBarcodeResolver::class)->resolve($this->tenant->id, $box->barcode);
        $again = $service->addPackageItem($order, $resolved->product, $resolved->package, '1', 2.5);
        $this->assertSame((string) $item->id, (string) $again->id);
        $this->assertSame('24.000', $item->fresh()->quantity);
        $this->assertSame($box->barcode, $item->fresh()->presentation_barcode);
        $this->product->update(['barcode' => '12345678']);
        $base = $service->addItem($order, $this->product, '1', 2.5);
        $resolved = app(ProductBarcodeResolver::class)->resolve($this->tenant->id, '12345678');
        $again = $service->addItem($order, $resolved->product, '1', 2.5);
        $this->assertSame((string) $base->id, (string) $again->id);
    }

    public function test_factor_name_and_barcode_changes_create_new_snapshots(): void
    {
        $service = app(OrderService::class);
        $order = $this->order();
        $box = $this->package();
        $old = $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $box->update(['factor' => 24]);
        $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $box->update(['barcode' => '12345678']);
        $new = $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $box->update(['name' => 'Caixa nova']);
        $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $this->assertSame(4, $order->items()->count());
        $this->assertSame(12, $old->fresh()->package_factor);
        $this->assertSame('7891234567890', $old->fresh()->presentation_barcode);
        $this->assertSame('12345678', $new->fresh()->presentation_barcode);
    }

    public function test_deleted_package_keeps_history_and_stock_uses_persisted_base_quantity(): void
    {
        $service = app(OrderService::class);
        $order = $this->order();
        $box = $this->package();
        $service->addItem($order, $this->product, '3', 2.5);
        $item = $service->addPackageItem($order, $this->product, $box, '1', 2.5);
        $box->delete();
        $this->assertNull($item->fresh()->product_package_id);
        $this->assertSame('package', $item->fresh()->sale_presentation_type);
        $this->assertSame('Caixa 12', $item->fresh()->package_name);
        $this->assertSame(12, $item->fresh()->package_factor);
        $this->assertSame('7891234567890', $item->fresh()->presentation_barcode);
        app(InventoryAdjustmentService::class)->adjust($this->product, $this->company->id, 'in', '20', null);
        $service->changeStatus($order, 'pending');
        $service->changeStatus($order, 'confirmed');
        $this->assertSame('15.000', Inventory::query()->sole()->reserved);
        $service->changeStatus($order, 'shipped');
        $this->assertSame('5.000', Inventory::query()->sole()->quantity_on_hand);
        $this->assertSame('0.000', Inventory::query()->sole()->reserved);
    }

    public static function invalidPackageQuantities(): array
    {
        return [['0'], ['-1'], ['0.5'], ['1e3'], ['100000000000']];
    }

    #[DataProvider('invalidPackageQuantities')]
    public function test_package_quantity_is_validated_without_partial_writes(string $quantity): void
    {
        $order = $this->order();
        try {
            app(OrderService::class)->addPackageItem($order, $this->product, $this->package(), $quantity, 2.5);
            $this->fail('Quantidade inválida aceita.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, $order->items()->count());
            $this->assertSame('0.00', $order->fresh()->total);
        }
    }

    public static function invalidPackages(): array
    {
        return [['tenant'], ['product'], ['kg'], ['factor'], ['inactive'], ['deleted']];
    }

    #[DataProvider('invalidPackages')]
    public function test_package_validation_defends_service_bypass(string $case): void
    {
        $box = $this->package();
        if ($case === 'tenant') {
            $box->update(['tenant_id' => Tenant::factory()->create()->id]);
        }
        if ($case === 'product') {
            $box->update(['product_id' => Product::factory()->create()->id]);
        }
        if ($case === 'kg') {
            $this->product->update(['unit' => 'KG']);
        }
        if ($case === 'factor') {
            $box->update(['factor' => 1]);
        }
        if ($case === 'inactive') {
            $this->product->update(['status' => 'inactive']);
        }
        if ($case === 'deleted') {
            $box->delete();
        }
        $order = $this->order();
        try {
            app(OrderService::class)->addPackageItem($order, $this->product, $box, '1', 2.5);
            $this->fail('Embalagem inválida aceita.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, $order->items()->count());
        }
    }

    public function test_package_price_must_be_explicit_and_representable_as_base_cents(): void
    {
        $order = $this->order();
        $box = $this->package();
        foreach ([null, 3.333333, -1.0] as $price) {
            try {
                app(OrderService::class)->addPackageItem($order, $this->product, $box, '1', $price);
                $this->fail('Preço incompatível aceito.');
            } catch (InvalidArgumentException) {
                $this->assertSame(0, $order->items()->count());
            }
        }
    }

    public function test_removal_is_by_item_id_and_stale_order_is_not_editable(): void
    {
        $service = app(OrderService::class);
        $order = $this->order();
        $first = $service->addItem($order, $this->product, '1', 5);
        $second = $service->addItem($order, $this->product, '1', 4);
        $service->removeItem($order, $first);
        $this->assertSame((string) $second->id, (string) $order->items()->sole()->id);
        $stale = $order->fresh();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'confirmed']);
        $this->expectException(InvalidArgumentException::class);
        $service->addItem($stale, $this->product, '1', 4);
    }

    public static function immutableFields(): array
    {
        return [['sale_presentation_type', 'base'], ['product_package_id', null], ['package_name', 'Outro'], ['package_factor', 24], ['presentation_barcode', null]];
    }

    #[DataProvider('immutableFields')]
    public function test_presentation_identity_cannot_be_reassigned(string $field, mixed $value): void
    {
        $item = app(OrderService::class)->addPackageItem($this->order(), $this->product, $this->package(), '1', 2.5);
        $this->expectException(InvalidArgumentException::class);
        $item->update([$field => $value]);
    }

    public function test_api_captures_server_snapshots_and_web_displays_all_presentations(): void
    {
        $box = $this->package();
        $response = $this->api()->postJson('/api/v1/orders', [
            'company_id' => (string) $this->company->id,
            'items' => [
                ['product_id' => (string) $this->product->id, 'quantity' => '3', 'unit_price' => '2.50'],
                ['product_id' => (string) $this->product->id, 'package_id' => (string) $box->id, 'quantity' => '1', 'unit_price' => '2.50', 'package_factor' => 999, 'package_name' => 'FALSO'],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.items');
        $order = Order::findOrFail($response->json('data.id'));
        $this->api()->getJson('/api/v1/orders/'.$order->id)->assertOk()
            ->assertJsonFragment(['sale_presentation_type' => 'package', 'package_factor' => 12, 'package_name' => 'Caixa 12', 'quantity' => '12.000']);
        $this->actingAs($this->user, 'web')->get(route('sales.orders.show', $order))->assertOk()->assertSee('Caixa 12')->assertSee('12,000 UN')->assertSee('3,000 UN');
        $this->actingAs($this->user, 'web')->post(route('sales.orders.items.store', $order), [
            'product_id' => (string) $this->product->id, 'package_id' => (string) $box->id, 'quantity' => '1', 'unit_price' => '2.50',
        ])->assertSessionHasNoErrors();
        $this->assertSame('24.000', $order->items()->where('sale_presentation_type', 'package')->sole()->quantity);
    }

    public function test_legacy_is_not_inferred_or_accumulated(): void
    {
        $order = $this->order();
        $item = OrderItem::factory()->forOrder($order, $this->product)->legacyPresentation()->create(['quantity' => '12', 'unit_price' => 2.5, 'total' => 30]);
        app(OrderService::class)->addItem($order, $this->product, '1', 2.5);
        $this->assertSame(2, $order->items()->count());
        $this->assertNull($item->fresh()->sale_presentation_type);
        $this->api()->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonFragment(['sale_presentation_type' => null, 'package_factor' => null]);
        $this->actingAs($this->user, 'web')->get(route('sales.orders.show', $order))->assertOk()->assertSee('apresentação histórica desconhecida');
    }

    public function test_order_lock_is_requested_inside_transaction_before_item_queries(): void
    {
        $order = $this->order();
        $connection = DB::connection();
        $original = $connection->getQueryGrammar();
        $grammar = new class($connection) extends SQLiteGrammar
        {
            public array $locks = [];

            protected function compileLock(Builder $query, $value)
            {
                $this->locks[] = [$query->from, $value, DB::transactionLevel()];

                return parent::compileLock($query, $value);
            }
        };
        try {
            $connection->setQueryGrammar($grammar);
            $service = app(OrderService::class);
            $first = $service->addItem($order, $this->product, '1', 2.5);
            $second = $service->addItem($order->fresh(), $this->product->fresh(), '2', 2.5);
            $this->assertSame((string) $first->id, (string) $second->id);
            $this->assertSame('3.000', $first->fresh()->quantity);
            $this->assertSame('orders', $grammar->locks[0][0]);
            $this->assertTrue($grammar->locks[0][1]);
            $this->assertGreaterThan(1, $grammar->locks[0][2]);
            $this->assertSame('order_items', $grammar->locks[1][0]);
            $this->assertTrue($grammar->locks[1][1]);
        } finally {
            $connection->setQueryGrammar($original);
        }
        // SQLite não executa FOR UPDATE; verificar somente sua compilação MySQL.
        $mysql = new MySqlConnection(fn () => throw new \RuntimeException('Sem conexão.'), 'isolated', '', ['driver' => 'mysql']);
        $builder = new Builder($mysql, new \Illuminate\Database\Query\Grammars\MySqlGrammar($mysql));
        $this->assertStringContainsString('for update', $builder->from('orders')->where('id', $order->id)->lockForUpdate()->toSql());
    }

    public function test_invalid_later_package_rolls_back_order_customer_and_previous_item(): void
    {
        $box = $this->package(['factor' => 1]);
        $this->api()->postJson('/api/v1/orders', [
            'company_id' => (string) $this->company->id, 'customer_name' => 'Cliente não persiste',
            'items' => [
                ['product_id' => (string) $this->product->id, 'quantity' => '1', 'unit_price' => '2.50'],
                ['product_id' => (string) $this->product->id, 'package_id' => (string) $box->id, 'quantity' => '1', 'unit_price' => '2.50'],
            ],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_web_removes_only_selected_line_of_same_product(): void
    {
        $order = $this->order();
        $service = app(OrderService::class);
        $base = $service->addItem($order, $this->product, '3', 2.5);
        $package = $service->addPackageItem($order, $this->product, $this->package(), '1', 2.5);
        $this->actingAs($this->user, 'web')->delete(route('sales.orders.items.destroy', [$order, $base]))->assertRedirect();
        $this->assertSame((string) $package->id, (string) $order->items()->sole()->id);
        $this->assertSame('30.00', $order->fresh()->total);
    }

    public function test_factory_states_are_explicit_and_coherent(): void
    {
        $order = $this->order();
        $box = $this->package();
        $base = OrderItem::factory()->forOrder($order, $this->product)->create();
        $package = OrderItem::factory()->forOrder($order, $this->product)->package($box)->create(['unit_price' => 2.5]);
        $legacy = OrderItem::factory()->forOrder($order, $this->product)->legacy()->create();
        $this->assertSame('base', $base->sale_presentation_type);
        $this->assertSame('package', $package->sale_presentation_type);
        $this->assertSame('12.000', $package->quantity);
        $this->assertSame('30.00', $package->total);
        $this->assertNull($legacy->sale_presentation_type);
        $this->assertNull($legacy->unit);
    }

    private function api(): static
    {
        return $this->withToken($this->user->createToken('pm-04c')->plainTextToken)->withHeader('X-Tenant-ID', $this->tenant->id);
    }
}
