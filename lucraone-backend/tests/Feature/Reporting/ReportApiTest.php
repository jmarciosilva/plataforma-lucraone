<?php

namespace Tests\Feature\Reporting;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F2.4 — Reporting & Analytics (API)
 */
class ReportApiTest extends TestCase
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

        // Data fixa: relatório com "últimos 30 dias" não pode depender de quando
        // a suíte roda.
        Carbon::setTestNow('2026-08-20 12:00:00');

        $this->tenant = Tenant::factory()->active()->create(['timezone' => 'UTC']);
        $this->outroTenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => 'REL-001',
            'name' => 'Café Relatório',
        ]);
        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_SALE,
            'currency' => 'BRL',
            'amount' => 50.00,
        ]);
        Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_COST,
            'currency' => 'BRL',
            'amount' => 30.00,
        ]);

        $this->user = User::factory()->forTenant($this->tenant)->create();
        $this->concederRelatorios($this->user);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * Relatório expõe faturamento agregado, então a API também exige permissão.
     */
    private function concederRelatorios(User $user): void
    {
        $papel = Role::factory()
            ->admin()
            ->forTenant($this->tenant->id)
            ->create(['id' => (string) Str::ulid()]);

        $permissao = Permission::factory()
            ->forTenant($this->tenant->id)
            ->create(['name' => 'view-reports', 'description' => 'view-reports']);

        $papel->grantPermission($permissao);
        $user->assignRole($papel, $this->tenant->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sales_report_counts_only_shipped_and_completed(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 100, '2026-08-19 10:00:00');
        $this->pedido(Order::STATUS_SHIPPED, 200, '2026-08-19 15:00:00');
        // Não contam como faturamento
        $this->pedido(Order::STATUS_CONFIRMED, 999, '2026-08-19 16:00:00');
        $this->pedido(Order::STATUS_CANCELLED, 888, '2026-08-19 17:00:00');

        $resposta = $this->requisicao()
            ->getJson('/api/v1/reports/sales?inicio=2026-08-01&fim=2026-08-20')
            ->assertOk();

        $this->assertEquals(300.0, $resposta->json('data.totais.faturamento'));
        $this->assertSame(2, $resposta->json('data.totais.pedidos_faturados'));
        $this->assertSame(4, $resposta->json('data.totais.pedidos_totais'));
        $this->assertSame(1, $resposta->json('data.totais.pedidos_cancelados'));
        $this->assertEquals(150.0, $resposta->json('data.totais.ticket_medio'));
        // Confirmado é carteira em aberto, não receita
        $this->assertEquals(999.0, $resposta->json('data.totais.carteira_em_aberto'));
    }

    public function test_sales_series_fills_empty_buckets(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 100, '2026-08-18 10:00:00');

        $serie = $this->requisicao()
            ->getJson('/api/v1/reports/sales?inicio=2026-08-16&fim=2026-08-20&granularidade=day')
            ->assertOk()
            ->json('data.serie');

        // 16, 17, 18, 19, 20 — cinco baldes, mesmo com venda em um só
        $this->assertCount(5, $serie);
        $this->assertEquals(0.0, $serie[0]['faturamento']);
        $this->assertEquals(100.0, $serie[2]['faturamento']);
        $this->assertEquals(0.0, $serie[4]['faturamento']);
    }

    public function test_date_filter_excludes_orders_outside_the_range(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 100, '2026-08-10 10:00:00');
        $this->pedido(Order::STATUS_COMPLETED, 500, '2026-07-10 10:00:00');

        $resposta = $this->requisicao()
            ->getJson('/api/v1/reports/sales?inicio=2026-08-01&fim=2026-08-20')
            ->assertOk();

        $this->assertEquals(100.0, $resposta->json('data.totais.faturamento'));
    }

    public function test_monthly_grouping_buckets_by_month(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 100, '2026-07-05 10:00:00');
        $this->pedido(Order::STATUS_COMPLETED, 150, '2026-07-20 10:00:00');
        $this->pedido(Order::STATUS_COMPLETED, 300, '2026-08-05 10:00:00');

        $serie = $this->requisicao()
            ->getJson('/api/v1/reports/sales?inicio=2026-07-01&fim=2026-08-20&granularidade=month')
            ->assertOk()
            ->json('data.serie');

        $this->assertCount(2, $serie);
        $this->assertEquals(250.0, $serie[0]['faturamento']);
        $this->assertEquals(300.0, $serie[1]['faturamento']);
    }

    public function test_weekly_grouping_buckets_by_monday(): void
    {
        // Mesma semana ISO: segunda 10/08 e sexta 14/08
        $this->pedido(Order::STATUS_COMPLETED, 100, '2026-08-10 10:00:00');
        $this->pedido(Order::STATUS_COMPLETED, 200, '2026-08-14 10:00:00');
        // Semana seguinte
        $this->pedido(Order::STATUS_COMPLETED, 700, '2026-08-18 10:00:00');

        $serie = $this->requisicao()
            ->getJson('/api/v1/reports/sales?inicio=2026-08-10&fim=2026-08-20&granularidade=week')
            ->assertOk()
            ->json('data.serie');

        $this->assertCount(2, $serie);
        $this->assertEquals(300.0, $serie[0]['faturamento']);
        $this->assertEquals(700.0, $serie[1]['faturamento']);
    }

    public function test_top_products_ranks_by_revenue(): void
    {
        $outro = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Chá Segundo',
            'sku' => 'REL-002',
        ]);

        $pedido = $this->pedido(Order::STATUS_COMPLETED, 400, '2026-08-18 10:00:00');
        OrderItem::factory()->forOrder($pedido, $this->product)->create([
            'quantity' => 2, 'unit_price' => 50, 'total' => 100,
        ]);
        OrderItem::factory()->forOrder($pedido, $outro)->create([
            'quantity' => 3, 'unit_price' => 100, 'total' => 300,
        ]);

        $top = $this->requisicao()
            ->getJson('/api/v1/reports/sales?inicio=2026-08-01&fim=2026-08-20')
            ->assertOk()
            ->json('data.top_produtos');

        $this->assertSame('Chá Segundo', $top[0]['nome']);
        $this->assertEquals(300.0, $top[0]['receita']);
        $this->assertSame('Café Relatório', $top[1]['nome']);
    }

    public function test_inventory_report_calculates_value_at_cost_and_sale(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 10,
            'reserved' => 2,
        ]);

        $resposta = $this->requisicao()->getJson('/api/v1/reports/inventory')->assertOk();

        $this->assertEquals(300.0, $resposta->json('data.resumo.valor_custo'));
        $this->assertEquals(500.0, $resposta->json('data.resumo.valor_venda'));
        $this->assertEquals(200.0, $resposta->json('data.resumo.margem_potencial'));
        $this->assertEqualsWithDelta(66.7, $resposta->json('data.resumo.margem_percentual'), 0.1);
    }

    public function test_inventory_report_flags_low_stock(): void
    {
        Inventory::factory()->forProduct($this->product)->create(['quantity_on_hand' => 2]);
        StockLevel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'company_id' => $this->company->id,
            'min_qty' => 1,
            'reorder_point' => 5,
            'max_qty' => 50,
        ]);

        $resposta = $this->requisicao()->getJson('/api/v1/reports/inventory')->assertOk();

        $this->assertSame(1, $resposta->json('data.por_situacao.baixo'));
        $this->assertSame(0, $resposta->json('data.por_situacao.excesso'));
    }

    public function test_customer_report_segments_by_order_count(): void
    {
        $vip = Customer::factory()->forCompany($this->company)->create(['name' => 'Cliente VIP']);
        $novo = Customer::factory()->forCompany($this->company)->create(['name' => 'Cliente Novo']);
        Customer::factory()->forCompany($this->company)->create(['name' => 'Nunca Comprou']);

        foreach ([100, 200, 300] as $valor) {
            $this->pedido(Order::STATUS_COMPLETED, $valor, '2026-08-15 10:00:00', $vip);
        }
        $this->pedido(Order::STATUS_COMPLETED, 50, '2026-08-15 10:00:00', $novo);

        $resposta = $this->requisicao()
            ->getJson('/api/v1/reports/customers?inicio=2026-08-01&fim=2026-08-20')
            ->assertOk();

        $this->assertSame(1, $resposta->json('data.segmentacao.vip'));
        $this->assertSame(1, $resposta->json('data.segmentacao.novo'));
        $this->assertSame(1, $resposta->json('data.segmentacao.sem_compra'));

        $top = $resposta->json('data.top_clientes');
        $this->assertSame('Cliente VIP', $top[0]['nome']);
        $this->assertEquals(600.0, $top[0]['total_gasto']);
        $this->assertSame(3, $top[0]['pedidos']);
        $this->assertEquals(200.0, $top[0]['ticket_medio']);
    }

    public function test_dashboard_summary_compares_against_previous_period(): void
    {
        // Período atual: 11 a 20/08 · anterior: 01 a 10/08
        $this->pedido(Order::STATUS_COMPLETED, 150, '2026-08-15 10:00:00');
        $this->pedido(Order::STATUS_COMPLETED, 100, '2026-08-05 10:00:00');

        $resposta = $this->requisicao()
            ->getJson('/api/v1/dashboard/summary?inicio=2026-08-11&fim=2026-08-20')
            ->assertOk();

        $this->assertEquals(150.0, $resposta->json('data.faturamento.valor'));
        $this->assertEquals(100.0, $resposta->json('data.faturamento.anterior'));
        $this->assertEquals(50.0, $resposta->json('data.faturamento.variacao'));
    }

    public function test_dashboard_summary_returns_null_variation_without_baseline(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 150, '2026-08-15 10:00:00');

        $resposta = $this->requisicao()
            ->getJson('/api/v1/dashboard/summary?inicio=2026-08-11&fim=2026-08-20')
            ->assertOk();

        // Sem base anterior não existe percentual — 100% faria o primeiro mês
        // de operação parecer crescimento.
        $this->assertNull($resposta->json('data.faturamento.variacao'));
    }

    public function test_trends_projects_growth(): void
    {
        foreach ([['2026-08-16', 100], ['2026-08-17', 200], ['2026-08-18', 300]] as [$dia, $valor]) {
            $this->pedido(Order::STATUS_COMPLETED, $valor, "{$dia} 10:00:00");
        }

        $resposta = $this->requisicao()
            ->getJson('/api/v1/analytics/trends?inicio=2026-08-16&fim=2026-08-18&granularidade=day')
            ->assertOk();

        $this->assertSame('alta', $resposta->json('data.tendencia'));
        $this->assertEquals(400.0, $resposta->json('data.projecao_proximo_periodo'));
        $this->assertTrue($resposta->json('data.confiavel'));
    }

    public function test_trends_never_projects_negative_revenue(): void
    {
        foreach ([['2026-08-16', 300], ['2026-08-17', 100], ['2026-08-18', 10]] as [$dia, $valor]) {
            $this->pedido(Order::STATUS_COMPLETED, $valor, "{$dia} 10:00:00");
        }

        $projecao = $this->requisicao()
            ->getJson('/api/v1/analytics/trends?inicio=2026-08-16&fim=2026-08-18&granularidade=day')
            ->assertOk()
            ->json('data.projecao_proximo_periodo');

        $this->assertGreaterThanOrEqual(0, $projecao);
    }

    public function test_reports_are_isolated_by_tenant(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 100, '2026-08-15 10:00:00');

        $outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        Order::factory()->forCompany($outraCompany)->status(Order::STATUS_COMPLETED)->create([
            'total' => 99999,
            'created_at' => '2026-08-15 10:00:00',
        ]);

        $resposta = $this->requisicao()
            ->getJson('/api/v1/reports/sales?inicio=2026-08-01&fim=2026-08-20')
            ->assertOk();

        $this->assertEquals(100.0, $resposta->json('data.totais.faturamento'));
    }

    public function test_invalid_granularity_is_rejected(): void
    {
        $this->requisicao()
            ->getJson('/api/v1/reports/sales?granularidade=decada')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['granularidade']);
    }

    public function test_reports_require_authentication(): void
    {
        $this->getJson('/api/v1/reports/sales')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
        $this->getJson('/api/v1/analytics/trends')->assertUnauthorized();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $semPermissao = User::factory()->forTenant($this->tenant)->create();
        $token = $semPermissao->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/reports/sales')
            ->assertForbidden();
    }

    private function requisicao()
    {
        return $this->withToken($this->token)->withHeader('X-Tenant-ID', $this->tenant->id);
    }

    private function pedido(string $status, float $total, string $criadoEm, ?Customer $cliente = null): Order
    {
        return Order::factory()
            ->forCompany($this->company)
            ->status($status)
            ->create([
                'total' => $total,
                'subtotal' => $total,
                'customer_id' => $cliente?->id,
                'created_at' => $criadoEm,
            ]);
    }
}
