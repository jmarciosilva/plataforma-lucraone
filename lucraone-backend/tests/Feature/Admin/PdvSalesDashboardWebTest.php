<?php

namespace Tests\Feature\Admin;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Pdv\Domain\Models\PdvReceivedSale;
use App\Modules\Pdv\Domain\Models\PdvReceivedSaleItem;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Dashboard de vendas do dia (retaguarda web).
 *
 * Dia fixo e fuso da filial (America/Sao_Paulo) para não depender de "hoje":
 * as vendas são criadas ao meio-dia local, bem dentro da janela do dia.
 */
#[Group('pdv')]
#[Group('pdv-sales')]
class PdvSalesDashboardWebTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Branch $filial;

    private Terminal $terminal;

    private User $admin;

    private string $dia = '2026-10-20';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Rede Mercado', 'timezone' => 'America/Sao_Paulo']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $empresa = Company::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'ACTIVE']);
        $this->filial = Branch::factory()->forCompany($empresa)->create(['name' => 'Loja Centro', 'timezone' => 'America/Sao_Paulo']);
        $this->terminal = Terminal::factory()->forBranch($this->filial)->create(['name' => 'Caixa 01', 'status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid()]);

        $this->admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    public function test_admin_ve_cards_com_total_do_dia(): void
    {
        $this->venda('50.00');
        $this->venda('30.00');
        $this->venda('10.00', cancelada: true);

        $resposta = $this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia]))
            ->assertOk()
            ->assertViewIs('pdv.sales.index');

        $cards = $resposta->viewData('cards');
        $this->assertSame(80.0, $cards['total_dia']);
        $this->assertSame(2, $cards['quantidade']);
        $this->assertSame(40.0, $cards['ticket_medio']);
        $this->assertSame(10.0, $cards['total_cancelado']);
    }

    public function test_cancelada_aparece_na_tabela_mas_fora_do_total(): void
    {
        $this->venda('25.00', cancelada: true);

        $resposta = $this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia]))
            ->assertOk()
            ->assertSee('cancelada');

        $this->assertSame(0.0, (float) $resposta->viewData('cards')['total_dia']);
    }

    public function test_filtra_por_terminal(): void
    {
        $outro = Terminal::factory()->forBranch($this->filial)->create(['name' => 'Caixa 02', 'status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid()]);
        $this->venda('100.00');
        $this->venda('40.00', terminal: $outro);

        $resposta = $this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia, 'terminal_id' => $this->terminal->id]))
            ->assertOk();

        $this->assertSame(100.0, $resposta->viewData('cards')['total_dia']);
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->get(route('pdv.sales.index', ['date' => $this->dia]))
            ->assertForbidden();
    }

    public function test_lucratividade_calcula_lucro_e_margem_sobre_o_custo(): void
    {
        $produto = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->filial->company_id,
            'sku' => 'PROD-LUCRO',
        ]);
        foreach (['cost' => '3.00', 'sale' => '5.00'] as $tipo => $valor) {
            Price::create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $this->tenant->id,
                'product_id' => $produto->id,
                'type' => $tipo,
                'currency' => 'BRL',
                'amount' => $valor,
            ]);
        }

        // Venda de 2 unidades a 5,00 (total 10,00); custo 3,00 => lucro 4,00, margem 40%.
        $venda = $this->venda('10.00');
        PdvReceivedSaleItem::create([
            'sale_id' => $venda->id,
            'line_number' => 1,
            'product_id' => $produto->id,
            'product_name' => 'Produto Lucro',
            'quantity' => '2.0000',
            'unit_price' => '5.00',
            'discount_amount' => '0.00',
            'total_amount' => '10.00',
        ]);

        $lucro = $this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia]))
            ->assertOk()
            ->viewData('lucratividade');

        $this->assertSame(4.0, $lucro['lucro']);
        $this->assertSame(40.0, $lucro['margem']);
        $this->assertSame(0, $lucro['itens_sem_custo']);
    }

    public function test_dashboard_geral_mostra_bloco_de_vendas_do_pdv_hoje(): void
    {
        // Venda de hoje (meio-dia no fuso da filial), que é o recorte do dashboard.
        $hoje = CarbonImmutable::now('America/Sao_Paulo')->setTime(12, 0)->utc();
        $this->venda('75.00', momento: $hoje);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('vendasPdv')
            ->assertSee('vendas de pdv hoje')
            ->assertSee('R$ 75,00');
    }

    public function test_dashboard_geral_oculta_bloco_para_quem_nao_ve_pdv(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('vendasPdv', null)
            ->assertDontSee('vendas de pdv hoje');
    }

    public function test_ping_conta_eventos_do_dia(): void
    {
        $this->venda('50.00');
        $this->venda('30.00');
        $this->venda('10.00', cancelada: true);

        $this->actingAs($this->admin)
            ->getJson(route('pdv.sales.ping', ['date' => $this->dia]))
            ->assertOk()
            ->assertJsonPath('eventos', 3)
            ->assertJsonStructure(['eventos', 'verificado_em']);
    }

    public function test_ping_respeita_permissao(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->getJson(route('pdv.sales.ping', ['date' => $this->dia]))
            ->assertForbidden();
    }

    public function test_vendas_por_forma_de_pagamento(): void
    {
        $this->venda('100.00', metodo: 'CASH');
        $this->venda('60.00', metodo: 'PIX');
        $this->venda('40.00', metodo: 'CASH');

        $pagamentos = collect($this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia]))
            ->assertOk()
            ->viewData('pagamentos'))->keyBy('metodo');

        $this->assertSame(140.0, $pagamentos['CASH']['total']);
        $this->assertSame(60.0, $pagamentos['PIX']['total']);
        $this->assertSame(70.0, $pagamentos['CASH']['percentual']); // 140 de 200
        $this->assertSame(0.0, $pagamentos['CARD_CREDIT']['total']); // sempre listado, mesmo zerado
    }

    public function test_valor_do_estoque_em_dinheiro(): void
    {
        $produto = Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->filial->company_id, 'unit' => 'UN']);
        foreach (['cost' => '4.00', 'sale' => '10.00'] as $tipo => $valor) {
            Price::create(['id' => (string) Str::ulid(), 'tenant_id' => $this->tenant->id, 'product_id' => $produto->id, 'type' => $tipo, 'currency' => 'BRL', 'amount' => $valor]);
        }
        Inventory::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $produto->id,
            'company_id' => $this->filial->company_id, 'quantity_on_hand' => '50', 'reserved' => '0',
        ]);

        $estoque = $this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia]))
            ->assertOk()
            ->viewData('estoque');

        $this->assertSame(200.0, $estoque['valor_custo']);   // 50 × 4,00
        $this->assertSame(500.0, $estoque['valor_venda']);   // 50 × 10,00
        $this->assertSame(60.0, $estoque['margem_potencial']); // (500−200)/500
    }

    public function test_simulador_de_promocao_calcula_ponto_de_equilibrio(): void
    {
        $produto = Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->filial->company_id, 'unit' => 'UN']);
        foreach (['cost' => '6.00', 'sale' => '10.00'] as $tipo => $valor) {
            Price::create(['id' => (string) Str::ulid(), 'tenant_id' => $this->tenant->id, 'product_id' => $produto->id, 'type' => $tipo, 'currency' => 'BRL', 'amount' => $valor]);
        }
        // 10 unidades vendidas hoje.
        $venda = $this->venda('100.00');
        PdvReceivedSaleItem::create([
            'sale_id' => $venda->id, 'line_number' => 1, 'product_id' => $produto->id,
            'product_name' => $produto->name, 'quantity' => '10.0000', 'unit_price' => '10.00',
            'discount_amount' => '0.00', 'total_amount' => '100.00',
        ]);

        // 20% de desconto: preço 10→8, custo 6. Margem atual 4, promo 2.
        // Lucro base = 10×4 = 40. Ponto de equilíbrio = 40/2 = 20 un → +10 un.
        $json = $this->actingAs($this->admin)
            ->getJson(route('pdv.sales.simular', ['date' => $this->dia, 'product_id' => (string) $produto->id, 'desconto' => 20]))
            ->assertOk()
            ->json();

        $this->assertTrue($json['ok']);
        $this->assertSame(8.0, (float) $json['preco_promocional']);
        $this->assertSame(40.0, (float) $json['lucro_base']);
        $this->assertSame(20.0, (float) $json['ponto_equilibrio_unidades']);
        $this->assertSame(10.0, (float) $json['unidades_adicionais']);
        $this->assertFalse($json['vende_abaixo_do_custo']);
    }

    public function test_simulador_recusa_desconto_invalido(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('pdv.sales.simular', ['date' => $this->dia, 'product_id' => 'x', 'desconto' => 0]))
            ->assertStatus(422);
    }

    public function test_periodo_7d_inclui_dias_anteriores(): void
    {
        // Uma venda hoje e outra 3 dias atrás (no fuso da filial).
        $hoje = CarbonImmutable::parse($this->dia.' 12:00:00', 'America/Sao_Paulo');
        $this->venda('100.00', momento: $hoje->utc());
        $this->venda('50.00', momento: $hoje->subDays(3)->utc());

        // hoje: só a de hoje.
        $this->assertSame(100.0, $this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia, 'periodo' => 'hoje']))
            ->viewData('cards')['total_dia']);

        // 7 dias: as duas.
        $this->assertSame(150.0, $this->actingAs($this->admin)
            ->get(route('pdv.sales.index', ['date' => $this->dia, 'periodo' => '7d']))
            ->viewData('cards')['total_dia']);
    }

    private function venda(string $total, bool $cancelada = false, ?Terminal $terminal = null, ?CarbonImmutable $momento = null, string $metodo = 'CASH'): PdvReceivedSale
    {
        $terminal ??= $this->terminal;
        $momento ??= CarbonImmutable::parse($this->dia.' 12:00:00', 'America/Sao_Paulo')->utc();

        return PdvReceivedSale::create([
            'id' => (string) Str::ulid(),
            'idempotency_key' => (string) Str::uuid(),
            'event_type' => $cancelada ? PdvReceivedSale::EVENT_CANCELLED : PdvReceivedSale::EVENT_CONFIRMED,
            'payload_version' => 1,
            'sale_id' => (string) Str::uuid(),
            'tenant_id' => $terminal->tenant_id,
            'company_id' => $terminal->company_id,
            'branch_id' => $terminal->branch_id,
            'terminal_id' => $terminal->id,
            'operator_name' => 'Operador Teste',
            'currency' => 'BRL',
            'subtotal_amount' => $total,
            'discount_amount' => '0.00',
            'total_amount' => $total,
            'payment_method' => $metodo,
            'payment_amount' => $total,
            'change_amount' => '0.00',
            'payment_declaration' => 'MANUAL',
            'confirmed_at' => $cancelada ? null : $momento,
            'cancelled_at' => $cancelada ? $momento : null,
            'created_at' => $momento,
            'received_at' => $momento,
        ]);
    }
}
