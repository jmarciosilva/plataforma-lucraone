<?php

namespace Tests\Feature\Sales;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Application\OrderService;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderItemSnapshotTest extends TestCase
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

    public static function units(): array
    {
        return [['UN', '2', '2.000', 'KG'], ['KG', '0.350', '0.350', 'UN']];
    }

    #[DataProvider('units')]
    public function test_history_is_preserved_in_database_api_and_web(string $unit, string $quantity, string $expected, string $later): void
    {
        $this->product->update(['unit' => $unit, 'sku' => 'ABC', 'name' => 'PRODUTO ANTIGO']);
        $response = $this->api()->postJson('/api/v1/orders', [
            'company_id' => $this->company->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 10, 'unit' => $later]],
        ])->assertCreated()->assertJsonPath('data.items.0.unit', $unit);
        $order = Order::findOrFail($response->json('data.id'));
        $this->product->update(['unit' => $later, 'sku' => 'XYZ', 'name' => 'PRODUTO NOVO']);
        $item = $order->items()->sole();
        $this->assertSame($unit, $item->unit);
        $this->assertSame($expected, $item->quantity);
        $this->assertSame('ABC', $item->sku);
        $this->assertSame('PRODUTO ANTIGO', $item->name);
        $this->api()->getJson('/api/v1/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.items.0.unit', $unit)->assertJsonPath('data.items.0.sku', 'ABC')
            ->assertJsonPath('data.items.0.name', 'PRODUTO ANTIGO');
        $this->actingAs($this->user, 'web')->get(route('sales.orders.show', $order))->assertOk()
            ->assertSee(number_format((float) $expected, 3, ',', '.').' '.$unit);
    }

    public function test_new_price_and_identity_preserve_previous_snapshot_in_separate_line(): void
    {
        $service = app(OrderService::class);
        $order = $service->create(['company_id' => $this->company->id]);
        $item = $service->addItem($order, $this->product, '2', 10);
        $before = $item->fresh()->getAttributes();
        $this->product->update(['sku' => 'NEW', 'name' => 'NOVO']);
        $updated = $service->addItem($order, $this->product, '1', 20);
        $this->assertSame($before, $item->fresh()->getAttributes());
        $this->assertNotSame((string) $item->id, (string) $updated->id);
        $this->assertSame('UN', $updated->unit);
        $this->assertSame('1.000', $updated->fresh()->quantity);
        $this->assertSame('20.00', $updated->fresh()->unit_price);
        $this->assertSame('40.00', $order->fresh()->total);
    }

    public static function unavailableUnits(): array
    {
        return [['UN', 'KG'], ['KG', 'UN'], [null, 'UN']];
    }

    #[DataProvider('unavailableUnits')]
    public function test_invalid_accumulation_is_atomic(?string $unit, string $current): void
    {
        $this->product->update(['unit' => $unit ?? $current]);
        $service = app(OrderService::class);
        $order = $service->create(['company_id' => $this->company->id]);
        $item = $service->addItem($order, $this->product, '2', 10);
        if ($unit === null) {
            DB::table('order_items')->where('id', $item->id)->update(['unit' => null]);
        }
        $this->product->update(['unit' => $current]);
        $before = $item->fresh()->getAttributes();
        $orderBefore = $order->fresh()->getAttributes();
        try {
            $service->addItem($order, $this->product, '1', 99);
            $this->fail('A unidade histórica indisponível ou divergente foi aceita.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString($unit === null ? 'unidade histórica' : 'unidade deste produto', $e->getMessage());
        }
        $this->assertSame($before, $item->fresh()->getAttributes());
        $this->assertSame($orderBefore, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public static function stockTransitions(): array
    {
        $cases = [];
        foreach ([null, 'KG'] as $unit) {
            foreach ([['pending', 'confirmed'], ['confirmed', 'shipped'], ['confirmed', 'cancelled']] as [$from, $to]) {
                $cases[] = [$unit, $from, $to];
            }
        }

        return $cases;
    }

    #[DataProvider('stockTransitions')]
    public function test_stock_rejects_unknown_or_changed_unit_atomically(?string $unit, string $from, string $to): void
    {
        $service = app(OrderService::class);
        $order = $service->create(['company_id' => $this->company->id]);
        // A valid first line must also be rolled back if a later line is invalid.
        $service->addItem($order, $this->product, '1', 10);
        $other = Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'unit' => 'KG']);
        $bad = $service->addItem($order, $other, '0.350', 10);
        foreach ([$this->product, $other] as $product) {
            app(InventoryAdjustmentService::class)->adjust($product, $this->company->id, 'in', '10', null);
        }
        $service->changeStatus($order, Order::STATUS_PENDING);
        if ($from === 'confirmed') {
            $service->changeStatus($order, Order::STATUS_CONFIRMED);
        }
        if ($unit === null) {
            DB::table('order_items')->where('id', $bad->id)->update(['unit' => null]);
        } else {
            $other->update(['unit' => 'UN']);
        }
        $balances = DB::table('inventories')->orderBy('id')->get()->toJson();
        $movements = DB::table('inventory_movements')->count();
        $before = $order->fresh()->getAttributes();
        $this->api()->putJson('/api/v1/orders/'.$order->id.'/status', ['status' => $to])->assertUnprocessable();
        $this->assertSame($balances, DB::table('inventories')->orderBy('id')->get()->toJson());
        $this->assertSame($movements, DB::table('inventory_movements')->count());
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertSame($unit, $bad->fresh()->unit);
    }

    #[DataProvider('units')]
    public function test_matching_unit_allows_reserve_ship_and_completion(string $unit, string $quantity, string $expected, string $later): void
    {
        $this->product->update(['unit' => $unit]);
        app(InventoryAdjustmentService::class)->adjust($this->product, $this->company->id, 'in', '10', null);
        $service = app(OrderService::class);
        $order = $service->create(['company_id' => $this->company->id]);
        $service->addItem($order, $this->product, $quantity, 10);
        foreach (['pending', 'confirmed', 'shipped', 'completed'] as $status) {
            $service->changeStatus($order, $status);
        }
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('0.000', Inventory::query()->sole()->reserved);
        $this->assertSame(number_format(10 - (float) $quantity, 3, '.', ''), Inventory::query()->sole()->quantity_on_hand);
    }

    public static function identityFields(): array
    {
        return [['unit', 'KG'], ['unit', null], ['sku', 'NEW'], ['name', 'NOVO'], ['product_id', 'OTHER']];
    }

    #[DataProvider('identityFields')]
    public function test_model_rejects_identity_changes(string $field, ?string $value): void
    {
        $order = app(OrderService::class)->create(['company_id' => $this->company->id]);
        $item = app(OrderService::class)->addItem($order, $this->product, '1', 10);
        $before = $item->fresh()->getAttributes();
        try {
            $item->update([$field => $value]);
            $this->fail('Identidade histórica alterada.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $item->fresh()->getAttributes());
        }
    }

    public function test_legacy_is_exposed_as_unknown_and_factory_matches_product(): void
    {
        $this->product->update(['unit' => 'KG']);
        $order = Order::factory()->forCompany($this->company)->create();
        $item = OrderItem::factory()->forOrder($order, $this->product)->create();
        $this->assertSame('KG', $item->unit);
        $item->delete();
        $legacy = OrderItem::factory()->forOrder($order, $this->product)->legacy()->create();
        $this->assertNull($legacy->unit);
        $this->api()->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('data.items.0.unit', null);
        $this->actingAs($this->user, 'web')->get(route('sales.orders.show', $order))->assertOk()->assertSee('unidade histórica desconhecida');
    }

    public function test_migration_preserves_legacy_without_default_and_rolls_back(): void
    {
        $this->assertTrue(Schema::hasColumn('order_items', 'unit'));
        $column = collect(Schema::getColumns('order_items'))->firstWhere('name', 'unit');
        $this->assertTrue($column['nullable']);
        $this->assertNull($column['default']);
        $migration = require database_path('migrations/2026_10_06_100000_add_unit_snapshot_to_order_items_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('order_items', 'unit'));
        $order = Order::factory()->forCompany($this->company)->create();
        $item = OrderItem::factory()->forOrder($order, $this->product)->make()->getAttributes();
        unset($item['unit']);
        $item['id'] = (string) Str::ulid();
        DB::table('order_items')->insert($item);
        $migration->up();
        $this->assertNull(DB::table('order_items')->where('id', $item['id'])->value('unit'));
        $this->assertSame(number_format($item['quantity'], 3, '.', ''), OrderItem::findOrFail($item['id'])->quantity);
    }

    public function test_web_rejects_changed_unit_without_modifying_order(): void
    {
        $service = app(OrderService::class);
        $order = $service->create(['company_id' => $this->company->id]);
        $item = $service->addItem($order, $this->product, '2', 10);
        $this->product->update(['unit' => 'KG']);
        $this->actingAs($this->user, 'web')->post(route('sales.orders.items.store', $order), [
            'product_id' => (string) $this->product->id, 'quantity' => '1', 'unit_price' => '99',
        ])->assertSessionHasErrors(['product_id' => 'A unidade deste produto foi alterada após a criação do pedido. O item não pode ser modificado.']);
        $this->assertSame('2.000', $item->fresh()->quantity);
        $this->assertSame('20.00', $order->fresh()->total);
    }

    public function test_default_factory_copies_associated_product_unit(): void
    {
        $item = OrderItem::factory()->create();
        $this->assertSame(Product::withoutGlobalScopes()->findOrFail($item->product_id)->unit, $item->unit);
    }

    public function test_migration_compiles_additive_mysql_ddl(): void
    {
        $connection = new MySqlConnection(function () {
            throw new \RuntimeException('Este teste não pode conectar ao MySQL.');
        }, 'isolated', '', ['driver' => 'mysql', 'version' => '8.4.0']);
        $connection->setSchemaGrammar(new MySqlGrammar($connection));
        $original = Schema::getFacadeRoot();
        $sql = [];
        try {
            Schema::shouldReceive('table')->twice()->andReturnUsing(function ($table, $callback) use ($connection, &$sql) {
                $blueprint = new Blueprint($connection, $table, $callback);
                $sql = [...$sql, ...$blueprint->toSql()];
            });
            $migration = require database_path('migrations/2026_10_06_100000_add_unit_snapshot_to_order_items_table.php');
            $migration->up();
            $migration->down();
        } finally {
            Schema::swap($original);
        }
        $this->assertCount(2, $sql);
        $this->assertSame('alter table `order_items` add `unit` varchar(6) null after `name`', $sql[0]);
        $this->assertSame('alter table `order_items` drop `unit`', $sql[1]);
    }

    private function api(): static
    {
        return $this->withToken($this->user->createToken('pm-04b')->plainTextToken)
            ->withHeader('X-Tenant-ID', $this->tenant->id);
    }
}
