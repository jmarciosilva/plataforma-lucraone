<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Domain\Models\Product;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationMargemTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_tem_snapshots_e_fks_seguras(): void
    {
        $this->assertTrue(Schema::hasColumns('prices', ['reference_cost_amount', 'effective_margin_percentage']));
        $this->assertTrue(Schema::hasColumns('price_histories', ['price_type', 'event_type', 'old_reference_cost_amount', 'new_reference_cost_amount', 'old_effective_margin_percentage', 'new_effective_margin_percentage']));
        $keys = collect(DB::select('PRAGMA foreign_key_list(price_histories)'))->keyBy('from');
        $this->assertSame('SET NULL', $keys['price_id']->on_delete);
        $this->assertSame('RESTRICT', $keys['tenant_id']->on_delete);
        $this->assertSame('RESTRICT', $keys['product_id']->on_delete);
        $this->assertSame('SET NULL', $keys['changed_by']->on_delete);
    }

    public function test_rollback_nao_descarta_snapshot_financeiro_existente(): void
    {
        $product = Product::factory()->create();
        DB::table('prices')->insert([
            'id' => (string) Str::ulid(), 'tenant_id' => $product->tenant_id,
            'product_id' => $product->id, 'currency' => 'BRL', 'type' => 'sale',
            'amount' => '3.00', 'reference_cost_amount' => '2.00',
            'effective_margin_percentage' => '50.0000',
        ]);
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_10_04_100000_add_historical_margin_snapshots.php'))->down();
    }

    public function test_ddl_da_migration_compila_para_mysql_sem_conectar(): void
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
            (require database_path('migrations/2026_10_04_100000_add_historical_margin_snapshots.php'))->up();
        } finally {
            Schema::swap($original);
        }
        $ddl = implode("\n", $sql);
        $this->assertStringContainsString('on delete set null', $ddl);
        $this->assertStringContainsString('on delete restrict', $ddl);
        $this->assertMatchesRegularExpression('/decimal\(18,\s*4\)/', $ddl);
        $this->assertStringContainsString('modify `price_id` char(26) null', $ddl);
        $this->assertStringContainsString('price_histories_product_timeline_idx', $ddl);
        $this->assertStringNotContainsString('drop index `prices_tenant_id_product_id_currency_type_unique`', $ddl);
    }

    public function test_backfill_nao_inventa_custo_margem_ou_data_passada(): void
    {
        $file = database_path('migrations/2026_10_04_100000_add_historical_margin_snapshots.php');
        $this->assertFileExists($file);
        $migration = require $file;
        $migration->down();
        $p = Product::factory()->create();
        $id = (string) Str::ulid();
        DB::table('prices')->insert(['id' => $id, 'tenant_id' => $p->tenant_id, 'product_id' => $p->id, 'currency' => 'BRL', 'type' => 'sale', 'amount' => '3.00']);
        $hid = (string) Str::ulid();
        DB::table('price_histories')->insert(['id' => $hid, 'tenant_id' => $p->tenant_id, 'product_id' => $p->id, 'price_id' => $id, 'currency' => 'BRL', 'old_amount' => '2.60', 'new_amount' => '3.00', 'changed_at' => '2026-01-01 00:00:00']);
        $migration->up();
        $h = DB::table('price_histories')->where('id', $hid)->first();
        $this->assertSame('sale', $h->price_type);
        $this->assertSame('amount_changed', $h->event_type);
        $this->assertSame('2026-01-01 00:00:00', $h->changed_at);
        foreach (['old_reference_cost_amount', 'new_reference_cost_amount', 'old_effective_margin_percentage', 'new_effective_margin_percentage'] as $field) {
            $this->assertNull($h->$field);
        }
        $this->assertNull(DB::table('prices')->where('id', $id)->value('effective_margin_percentage'));
    }
}
