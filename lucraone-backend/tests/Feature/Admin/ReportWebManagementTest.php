<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
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
 * F2.4 — Reporting & Analytics (painel administrativo)
 */
class ReportWebManagementTest extends TestCase
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

        Carbon::setTestNow('2026-08-20 12:00:00');

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta', 'timezone' => 'UTC']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        $this->company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create([
            'trade_name' => 'Casa Alta',
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'sku' => 'REL-100',
            'name' => 'Vinho Relatório',
        ]);
        Price::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $this->product->id,
            'type' => Price::TYPE_COST,
            'currency' => 'BRL',
            'amount' => 20.00,
        ]);

        $this->admin = User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Ana Admin']);
        $this->adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantAtual->id)
            ->create(['id' => (string) Str::ulid()]);

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_visao_geral_renderiza_kpis_reais_e_help(): void
    {
        $cliente = Customer::factory()->forCompany($this->company)->create(['name' => 'Bar do Zé']);
        $pedido = $this->pedido(Order::STATUS_COMPLETED, 1250.00, '2026-08-18 10:00:00', $cliente);
        OrderItem::factory()->forOrder($pedido, $this->product)->create([
            'quantity' => 5, 'unit_price' => 250, 'total' => 1250,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('relatórios')
            ->assertSee('ajuda de relatórios')
            ->assertSee('1.250,00')
            ->assertSee('Vinho Relatório')
            ->assertSee('Bar do Zé');
    }

    public function test_relatorio_de_vendas_ignora_pedido_nao_faturado(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 500.00, '2026-08-18 10:00:00');
        $this->pedido(Order::STATUS_DRAFT, 7777.00, '2026-08-18 11:00:00');

        $resposta = $this->actingAs($this->admin)
            ->get(route('reports.sales', ['inicio' => '2026-08-01', 'fim' => '2026-08-20']))
            ->assertOk();

        $resposta->assertSee('500,00');
        // Rascunho aparece como carteira, nunca somado ao faturamento
        $resposta->assertDontSee('8.277,00');
    }

    public function test_filtro_de_periodo_altera_o_resultado(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 999.00, '2026-07-05 10:00:00');

        // Julho está fora do recorte de agosto
        $this->actingAs($this->admin)
            ->get(route('reports.sales', ['inicio' => '2026-08-01', 'fim' => '2026-08-20']))
            ->assertOk()
            ->assertDontSee('999,00');

        $this->actingAs($this->admin)
            ->get(route('reports.sales', ['inicio' => '2026-07-01', 'fim' => '2026-07-31']))
            ->assertOk()
            ->assertSee('999,00');
    }

    public function test_relatorio_de_estoque_mostra_valor_imobilizado(): void
    {
        Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 10,
            'reserved' => 0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('Vinho Relatório')
            // 10 unidades a 20,00 de custo
            ->assertSee('200,00');
    }

    public function test_relatorio_de_clientes_lista_quem_mais_gastou(): void
    {
        $cliente = Customer::factory()->forCompany($this->company)->create(['name' => 'Padaria Aurora']);
        $this->pedido(Order::STATUS_COMPLETED, 800.00, '2026-08-18 10:00:00', $cliente);

        $this->actingAs($this->admin)
            ->get(route('reports.customers', ['inicio' => '2026-08-01', 'fim' => '2026-08-20']))
            ->assertOk()
            ->assertSee('Padaria Aurora')
            ->assertSee('800,00');
    }

    public function test_relatorios_isolam_por_tenant(): void
    {
        $meuCliente = Customer::factory()->forCompany($this->company)->create(['name' => 'Cliente Daqui']);
        $this->pedido(Order::STATUS_COMPLETED, 100.00, '2026-08-18 10:00:00', $meuCliente);

        $outraCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        $outroCliente = Customer::factory()->forCompany($outraCompany)->create(['name' => 'Cliente De Fora']);
        Order::factory()->forCompany($outraCompany)->status(Order::STATUS_COMPLETED)->create([
            'customer_id' => $outroCliente->id,
            'total' => 55555.00,
            'created_at' => '2026-08-18 10:00:00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.customers', ['inicio' => '2026-08-01', 'fim' => '2026-08-20']))
            ->assertOk()
            ->assertSee('Cliente Daqui')
            ->assertDontSee('Cliente De Fora')
            ->assertDontSee('55.555,00');
    }

    public function test_exportacao_csv_de_vendas(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 320.00, '2026-08-18 10:00:00');

        $resposta = $this->actingAs($this->admin)
            ->get(route('reports.export', [
                'tipo' => 'sales',
                'inicio' => '2026-08-18',
                'fim' => '2026-08-18',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $conteudo = $resposta->streamedContent();

        $this->assertStringContainsString('faturamento', $conteudo);
        $this->assertStringContainsString('320,00', $conteudo);
    }

    public function test_exportacao_csv_de_estoque(): void
    {
        Inventory::factory()->forProduct($this->product)->create(['quantity_on_hand' => 4]);

        $conteudo = $this->actingAs($this->admin)
            ->get(route('reports.export', ['tipo' => 'inventory']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('REL-100', $conteudo);
        $this->assertStringContainsString('Vinho Relatório', $conteudo);
    }

    public function test_tipo_de_exportacao_invalido_da_404(): void
    {
        $this->actingAs($this->admin)
            ->get('/reports/faturas/export')
            ->assertNotFound();
    }

    public function test_dashboard_mostra_faixa_comercial(): void
    {
        $this->pedido(Order::STATUS_COMPLETED, 640.00, '2026-08-18 10:00:00');

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('o negócio nos últimos 30 dias')
            ->assertSee('640,00');
    }

    public function test_usuario_sem_permissao_recebe_403_e_nao_ve_faixa_comercial(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('reports.sales'))->assertForbidden();
        $this->actingAs($usuario)->get(route('reports.inventory'))->assertForbidden();
        $this->actingAs($usuario)->get(route('reports.customers'))->assertForbidden();

        // O dashboard continua abrindo, só sem os números comerciais
        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('o negócio nos últimos 30 dias');
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        foreach (['view-reports'] as $permissionName) {
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
