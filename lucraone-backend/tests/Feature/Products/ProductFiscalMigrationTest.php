<?php

namespace Tests\Feature\Products;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductFiscalMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const FIELDS = ['ncm_code' => 8, 'cest_code' => 7, 'default_origin_code' => 1];

    private function migration(): object
    {
        return require database_path('migrations/2026_10_06_120000_add_fiscal_classification_to_products_table.php');
    }

    public function test_columns_are_nullable_without_defaults_indexes_or_foreign_keys(): void
    {
        $columns = collect(Schema::getColumns('products'))->keyBy('name');
        foreach (self::FIELDS as $field => $length) {
            $this->assertArrayHasKey($field, $columns->all());
            $this->assertTrue($columns[$field]['nullable']);
            $this->assertNull($columns[$field]['default']);
            $this->assertSame('varchar', $columns[$field]['type_name']);
            foreach (Schema::getIndexes('products') as $index) {
                $this->assertNotContains($field, $index['columns']);
            }
            foreach (Schema::getForeignKeys('products') as $fk) {
                $this->assertNotContains($field, $fk['columns']);
            }
        }
    }

    public function test_rollback_removes_only_new_columns_and_up_does_not_invent_legacy_data(): void
    {
        $tenant = Tenant::factory()->create();
        $company = Company::factory()->create(['tenant_id' => $tenant->id]);
        $this->migration()->down();
        $columns = Schema::getColumnListing('products');
        $indexes = Schema::getIndexes('products');
        $fks = Schema::getForeignKeys('products');
        foreach (self::FIELDS as $field => $length) {
            $this->assertNotContains($field, $columns);
        }
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'unit' => 'KG']);
        $before = (array) DB::table('products')->where('id', $product->id)->first();
        $this->migration()->up();
        $after = (array) DB::table('products')->where('id', $product->id)->first();
        foreach (self::FIELDS as $field => $length) {
            $this->assertArrayHasKey($field, $after);
            $this->assertNull($after[$field]);
        }
        $this->assertSame($before, array_diff_key($after, self::FIELDS));
        $this->assertEqualsCanonicalizing([...$columns, ...array_keys(self::FIELDS)], Schema::getColumnListing('products'));
        $this->assertSame($indexes, Schema::getIndexes('products'));
        $this->assertSame($fks, Schema::getForeignKeys('products'));
        $this->migration()->down();
        $this->assertSame($before, (array) DB::table('products')->where('id', $product->id)->first());
        $this->assertSame($columns, Schema::getColumnListing('products'));
        $this->migration()->up();
    }

    public function test_mysql_84_ddl_has_exact_lengths_and_no_business_defaults_or_constraints(): void
    {
        $connection = new MySqlConnection(fn () => throw new \RuntimeException('Sem conexão.'), 'isolated', '', ['driver' => 'mysql', 'version' => '8.4.0']);
        $connection->setSchemaGrammar(new MySqlGrammar($connection));
        $original = Schema::getFacadeRoot();
        $sql = [];
        try {
            Schema::shouldReceive('table')->twice()->andReturnUsing(function ($table, $callback) use ($connection, &$sql) {
                $this->assertSame('products', $table);
                $sql = [...$sql, ...(new Blueprint($connection, $table, $callback))->toSql()];
            });
            $this->migration()->up();
            $up = implode("\n", $sql);
            $sql = [];
            $this->migration()->down();
            $down = implode("\n", $sql);
        } finally {
            Schema::swap($original);
        }
        foreach (self::FIELDS as $field => $length) {
            $this->assertStringContainsString("`{$field}` varchar({$length}) null", $up);
            $this->assertStringContainsString("drop `{$field}`", $down);
        }
        foreach ([' default ', 'index', 'unique', 'foreign', 'update ', 'delete '] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($up));
        }
    }
}
