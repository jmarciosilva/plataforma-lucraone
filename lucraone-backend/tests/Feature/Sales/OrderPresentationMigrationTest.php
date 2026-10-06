<?php

namespace Tests\Feature\Sales;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
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
use Tests\TestCase;

class OrderPresentationMigrationTest extends TestCase
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

    private function migration(): object
    {
        return require database_path('migrations/2026_10_06_110000_add_sale_presentations_to_order_items_table.php');
    }

    public function test_columns_indexes_and_nullable_set_null_reference(): void
    {
        $columns = collect(Schema::getColumns('order_items'))->keyBy('name');
        foreach (['sale_presentation_type', 'product_package_id', 'package_name', 'package_factor', 'presentation_barcode'] as $field) {
            $this->assertArrayHasKey($field, $columns->all());
            $this->assertTrue($columns[$field]['nullable']);
            $this->assertNull($columns[$field]['default']);
        }
        $indexes = collect(Schema::getIndexes('order_items'))->keyBy('name');
        $this->assertArrayNotHasKey('order_items_order_id_product_id_unique', $indexes->all());
        $this->assertFalse($indexes['order_items_order_product_index']['unique']);
        $fk = collect(Schema::getForeignKeys('order_items'))->first(fn ($fk) => $fk['columns'] === ['product_package_id']);
        $this->assertNotNull($fk);
        $this->assertSame('set null', strtolower($fk['on_delete']));
    }

    public function test_safe_rollback_and_up_leave_legacy_unknown(): void
    {
        $order = Order::factory()->forCompany($this->company)->create();
        $item = OrderItem::factory()->forOrder($order, $this->product)->legacyPresentation()->create();
        $before = $item->fresh()->only(['product_id', 'sku', 'name', 'unit', 'quantity', 'unit_price', 'total']);
        $this->migration()->down();
        $this->assertFalse(Schema::hasColumn('order_items', 'sale_presentation_type'));
        $indexes = collect(Schema::getIndexes('order_items'))->keyBy('name');
        $this->assertTrue($indexes['order_items_order_id_product_id_unique']['unique']);
        $this->migration()->up();
        $this->assertSame($before, $item->fresh()->only(array_keys($before)));
        foreach (['sale_presentation_type', 'product_package_id', 'package_name', 'package_factor', 'presentation_barcode'] as $field) {
            $this->assertNull($item->fresh()->$field);
        }
    }

    public function test_rollback_refuses_duplicates_before_any_ddl_or_data_change(): void
    {
        $order = Order::factory()->forCompany($this->company)->create();
        OrderItem::factory()->forOrder($order, $this->product)->count(2)->create();
        $before = DB::table('order_items')->get()->toJson();
        try {
            $this->migration()->down();
            $this->fail('Rollback destrutivo aceito.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('múltiplas linhas', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('order_items', 'sale_presentation_type'));
        $this->assertSame($before, DB::table('order_items')->get()->toJson());
    }

    public function test_mysql_ddl_keeps_fk_indexes_and_set_null_in_safe_order(): void
    {
        $connection = new MySqlConnection(fn () => throw new \RuntimeException('Sem conexão.'), 'isolated', '', ['driver' => 'mysql', 'version' => '8.4.0']);
        $connection->setSchemaGrammar(new MySqlGrammar($connection));
        $original = Schema::getFacadeRoot();
        $sql = [];
        try {
            Schema::shouldReceive('table')->times(4)->andReturnUsing(function ($table, $callback) use ($connection, &$sql) {
                $blueprint = new Blueprint($connection, $table, $callback);
                $sql = [...$sql, ...$blueprint->toSql()];
            });
            $this->migration()->up();
            $up = implode("\n", $sql);
            $sql = [];
            $this->migration()->down();
            $down = implode("\n", $sql);
        } finally {
            Schema::swap($original);
        }
        $this->assertStringContainsString('`sale_presentation_type` varchar(16) null', $up);
        $this->assertStringContainsString('`product_package_id` char(26) null', $up);
        $this->assertStringContainsString('`package_factor` int unsigned null', $up);
        $this->assertStringContainsString('`presentation_barcode` varchar(14) null', $up);
        $this->assertStringContainsString('on delete set null', $up);
        $this->assertStringNotContainsString('default', strtolower($up));
        $this->assertLessThan(strpos($up, 'drop index `order_items_order_id_product_id_unique`'), strpos($up, 'add index `order_items_order_product_index`'));
        $this->assertLessThan(strpos($down, 'drop index `order_items_order_product_index`'), strpos($down, 'add unique `order_items_order_id_product_id_unique`'));
        $this->assertStringContainsString('drop foreign key `order_items_product_package_id_foreign`', $down);
    }
}
