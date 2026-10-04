<?php

namespace Tests\Feature\Products;

use App\Modules\Automation\Application\Actions\UpdatePriceAction;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Application\RegistrarPreco;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\PriceHistory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HistoricoMargemTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        $company = Company::factory()->forCurrentTenant($this->tenant->id)->create();
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $company->id]);
        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $p = app(ProvisionarEstabelecimento::class);
        $p->provisionarMatriz($this->tenant);
        $p->atribuirAdministrador($this->tenant, $this->admin);
        app(TenantContext::class)->set($this->tenant->id);
    }

    private function salvar(string $type, string $amount, string $currency = 'BRL'): Price
    {
        return app(RegistrarPreco::class)->salvar($this->product, compact('type', 'amount', 'currency'), $this->admin->id, 'teste histórico');
    }

    public function test_initial_guarda_snapshot_e_identidade(): void
    {
        $cost = $this->salvar('cost', '2.00');
        $sale = $this->salvar('sale', '3.00');
        $this->assertSame('2.00', $sale->reference_cost_amount);
        $this->assertSame('50.0000', $sale->effective_margin_percentage);
        $h = $sale->histories()->sole();
        $this->assertSame('initial', $h->event_type);
        $this->assertSame('sale', $h->price_type);
        $this->assertSame((string) $this->tenant->id, $h->tenant_id);
        $this->assertSame((string) $this->product->id, $h->product_id);
        $this->assertSame((string) $this->admin->id, $h->changed_by);
        $this->assertSame('teste histórico', $h->reason);
        $this->assertNull($h->old_amount);
        $this->assertNull($h->old_reference_cost_amount);
        $this->assertNull($h->old_effective_margin_percentage);
        $this->assertSame('3.00', $h->new_amount);
        $this->assertSame('2.00', $h->new_reference_cost_amount);
        $this->assertSame('50.0000', $h->new_effective_margin_percentage);
        $this->assertNotNull($h->changed_at);
        $this->assertNull($cost->histories()->sole()->new_effective_margin_percentage);
    }

    public function test_alteracao_venda_preserva_passado_e_valor_manual(): void
    {
        $this->salvar('cost', '2.00');
        $sale = $this->salvar('sale', '3.00');
        $initial = $sale->histories()->sole();
        $sale = $this->salvar('sale', '2.60');
        $h = $sale->histories()->where('event_type', 'amount_changed')->sole();
        $this->assertSame('3.00', $h->old_amount);
        $this->assertSame('2.60', $h->new_amount);
        $this->assertSame('2.00', $h->old_reference_cost_amount);
        $this->assertSame('2.00', $h->new_reference_cost_amount);
        $this->assertSame('50.0000', $h->old_effective_margin_percentage);
        $this->assertSame('30.0000', $h->new_effective_margin_percentage);
        $this->salvar('cost', '2.50');
        $this->assertSame('50.0000', $initial->fresh()->new_effective_margin_percentage);
        $this->assertSame('30.0000', $h->fresh()->new_effective_margin_percentage);
        $this->assertSame('2.60', $sale->fresh()->amount);
        $this->assertSame('4.0000', $sale->fresh()->effective_margin_percentage);
    }

    public function test_custo_atualiza_contexto_da_venda_na_mesma_transacao_e_moeda(): void
    {
        $this->salvar('cost', '2.00');
        $sale = $this->salvar('sale', '3.00');
        $this->salvar('cost', '10.00', 'USD');
        $usd = $this->salvar('sale', '15.00', 'USD');
        $cost = $this->salvar('cost', '2.50');
        $h = $sale->histories()->where('event_type', 'reference_cost_changed')->sole();
        $this->assertSame('3.00', $h->old_amount);
        $this->assertSame('3.00', $h->new_amount);
        $this->assertSame('2.00', $h->old_reference_cost_amount);
        $this->assertSame('2.50', $h->new_reference_cost_amount);
        $this->assertSame('50.0000', $h->old_effective_margin_percentage);
        $this->assertSame('20.0000', $h->new_effective_margin_percentage);
        $this->assertSame('20.0000', $sale->fresh()->effective_margin_percentage);
        $this->assertTrue($cost->histories()->where('event_type', 'amount_changed')->sole()->changed_at->equalTo($h->changed_at));
        $this->assertSame('50.0000', $usd->fresh()->effective_margin_percentage);
        $this->assertSame(1, $usd->histories()->count());
        $this->assertSame('20.0000', $sale->fresh()->margin_percentage);
    }

    public function test_criar_custo_depois_da_venda_registra_contexto_antes_desconhecido(): void
    {
        $sale = $this->salvar('sale', '3.00');
        $this->assertNull($sale->effective_margin_percentage);
        $this->salvar('cost', '2.00');
        $h = $sale->histories()->where('event_type', 'reference_cost_changed')->sole();
        $this->assertNull($h->old_reference_cost_amount);
        $this->assertNull($h->old_effective_margin_percentage);
        $this->assertSame('50.0000', $h->new_effective_margin_percentage);
    }

    public static function cenarios(): array
    {
        return [[null, '3.00', null], ['0.00', '3.00', null], ['10.00', '8.00', '-20.0000'], ['2.20', '2.60', '18.1818'], ['0.03', '0.04', '33.3333'], ['2.00', '2.00', '0.0000'], ['0.01', '9999999999.99', '99999999999800.0000']];
    }

    #[DataProvider('cenarios')]
    public function test_precisao_decimal_sem_float(?string $cost, string $sale, ?string $expected): void
    {
        if ($cost !== null) {
            $this->salvar('cost', $cost);
        }
        $p = $this->salvar('sale', $sale);
        $this->assertSame($expected, $p->effective_margin_percentage);
        $this->assertSame($expected, $p->histories()->sole()->new_effective_margin_percentage);
        $this->assertSame($cost, $p->reference_cost_amount);
    }

    public function test_custo_de_outra_moeda_nao_participa(): void
    {
        $this->salvar('cost', '2.00', 'USD');
        $sale = $this->salvar('sale', '3.00');
        $this->assertNull($sale->reference_cost_amount);
        $this->assertNull($sale->effective_margin_percentage);
    }

    public function test_remover_venda_preserva_todos_eventos_sem_dependencia_de_price(): void
    {
        $this->salvar('cost', '2.00');
        $sale = $this->salvar('sale', '3.00');
        $this->salvar('sale', '2.60');
        $id = $sale->id;
        app(RegistrarPreco::class)->remover($sale, $this->admin->id, 'remoção');
        $this->assertDatabaseMissing('prices', ['id' => $id]);
        $events = PriceHistory::where('product_id', $this->product->id)->where('price_type', 'sale')->orderBy('changed_at')->orderBy('id')->get();
        $this->assertSame(['initial', 'amount_changed', 'price_removed'], $events->pluck('event_type')->all());
        foreach ($events as $h) {
            $this->assertNull($h->price_id);
            $this->assertSame((string) $this->product->id, $h->product_id);
            $this->assertSame((string) $this->tenant->id, $h->tenant_id);
            $this->assertSame('BRL', $h->currency);
        }
        $removed = $events->last();
        $this->assertSame('2.60', $removed->old_amount);
        $this->assertSame('30.0000', $removed->old_effective_margin_percentage);
        $this->assertNull($removed->new_amount);
        $this->assertNull($removed->new_reference_cost_amount);
        $this->assertNull($removed->new_effective_margin_percentage);
    }

    public function test_remover_custo_invalida_margem_atual_sem_apagar_passado(): void
    {
        $cost = $this->salvar('cost', '2.00');
        $sale = $this->salvar('sale', '3.00');
        app(RegistrarPreco::class)->remover($cost);
        $this->assertNull($sale->fresh()->reference_cost_amount);
        $this->assertNull($sale->fresh()->effective_margin_percentage);
        $this->assertSame('50.0000', $sale->histories()->where('event_type', 'initial')->sole()->new_effective_margin_percentage);
    }

    public function test_rollback_reverte_custo_venda_e_eventos_quando_historico_falha(): void
    {
        $this->salvar('cost', '2.00');
        $sale = $this->salvar('sale', '3.00');
        $before = PriceHistory::count();
        // Falha real de FK do usuário, depois da mudança do preço dentro da transação.
        try {
            app(RegistrarPreco::class)->salvar($this->product, ['type' => 'cost', 'amount' => '2.50', 'currency' => 'BRL'], str_repeat('Z', 26));
            $this->fail('A FK deveria impedir o evento');
        } catch (QueryException $e) {
            $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
        }
        $this->assertSame('2.00', Price::cost()->sole()->amount);
        $this->assertSame('50.0000', $sale->fresh()->effective_margin_percentage);
        $this->assertSame($before, PriceHistory::count());
    }

    public function test_outro_tenant_nao_pode_receber_gravacao_ou_remocao(): void
    {
        $sale = $this->salvar('sale', '3.00');
        $other = Tenant::factory()->active()->create();
        app(TenantContext::class)->set($other->id);
        try {
            app(RegistrarPreco::class)->remover($sale);
            $this->fail('Tenant errado deveria ser recusado');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('prices', ['id' => $sale->id, 'amount' => '3.00']);
        }
        $this->assertSame(0, PriceHistory::count());
    }

    public function test_api_registra_initial_alteracao_e_remocao(): void
    {
        $this->salvar('cost', '2.00');
        $this->withToken($this->admin->createToken('teste')->plainTextToken)->withHeader('X-Tenant-ID', $this->tenant->id);
        $dados = ['product_id' => $this->product->id, 'type' => 'sale', 'currency' => 'BRL', 'amount' => '3.00'];
        $id = $this->postJson('/api/v1/prices', $dados)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/prices', [...$dados, 'amount' => '2.60'])->assertCreated();
        $this->assertSame(2, PriceHistory::where('price_id', $id)->count());
        $this->getJson('/api/v1/prices/history/'.$this->product->id)->assertOk()->assertJsonFragment(['event_type' => 'initial']);
        $this->deleteJson('/api/v1/prices/'.$id)->assertSuccessful();
        $this->assertSame(3, PriceHistory::where('price_type', 'sale')->whereNull('price_id')->count());
    }

    public function test_web_registra_initial_e_tabela_nao_recalcula_snapshot_legado(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('catalog.products.prices.store', $this->product), ['type' => 'sale', 'currency' => 'BRL', 'amount' => '3,00', 'reason' => 'inicial'])->assertSessionHasNoErrors();
        $this->assertSame('initial', PriceHistory::sole()->event_type);
        // Alteração direta simula legado; não deve preencher o snapshot retroativamente.
        Price::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'type' => 'cost', 'currency' => 'BRL', 'amount' => '2.00']);
        $this->assertNull(Price::sale()->sole()->margin_percentage);
        $this->get(route('catalog.products.show', $this->product))->assertOk()->assertSee('Preço inicial');
    }

    public function test_arquivamento_preserva_e_hard_delete_e_bloqueado(): void
    {
        $this->salvar('sale', '3.00');
        $this->product->delete();
        $this->assertSame(1, PriceHistory::count());
        $this->assertNotNull(PriceHistory::sole()->product);
        $this->tenant->delete();
        $this->assertSame(1, PriceHistory::count());
        foreach ([$this->product, $this->tenant] as $entity) {
            try {
                DB::transaction(fn () => $entity->forceDelete());
                $this->fail('Hard delete deveria ser bloqueado');
            } catch (QueryException $e) {
                $this->assertStringContainsString('FOREIGN KEY', $e->getMessage());
            }
        }
        $this->assertSame(1, PriceHistory::count());
    }

    public function test_api_nao_aceita_produto_de_outro_tenant_ou_snapshots_do_payload(): void
    {
        $other = Product::factory()->create();
        $this->withToken($this->admin->createToken('teste')->plainTextToken)->withHeader('X-Tenant-ID', $this->tenant->id);
        $dados = ['type' => 'sale', 'currency' => 'BRL', 'amount' => '3.00'];
        $this->postJson('/api/v1/prices', [...$dados, 'product_id' => $other->id])->assertNotFound();
        $this->assertDatabaseCount('prices', 0);
        $this->assertDatabaseCount('price_histories', 0);
        $this->salvar('cost', '2.00');
        $this->postJson('/api/v1/prices', [...$dados, 'product_id' => $this->product->id,
            'tenant_id' => $other->tenant_id, 'effective_margin_percentage' => '999', 'reference_cost_amount' => '0.01',
        ])->assertCreated()->assertJsonPath('data.effective_margin_percentage', '50.0000');
        $sale = Price::sale()->sole();
        $this->assertSame((string) $this->tenant->id, $sale->tenant_id);
        $this->assertSame('2.00', $sale->reference_cost_amount);
    }

    public function test_mudanca_custo_nao_afeta_outro_produto_do_mesmo_tenant(): void
    {
        $other = Product::factory()->create(['tenant_id' => $this->tenant->id, 'company_id' => $this->product->company_id]);
        app(RegistrarPreco::class)->salvar($other, ['type' => 'cost', 'currency' => 'BRL', 'amount' => '10.00']);
        $sale = app(RegistrarPreco::class)->salvar($other, ['type' => 'sale', 'currency' => 'BRL', 'amount' => '15.00']);
        $this->salvar('cost', '2.00');
        $this->salvar('cost', '2.50');
        $this->assertSame('50.0000', $sale->fresh()->effective_margin_percentage);
        $this->assertSame(1, $sale->histories()->count());
    }

    public function test_automacao_gera_o_mesmo_snapshot_de_margem(): void
    {
        $this->salvar('cost', '2.00');
        $sale = $this->salvar('sale', '3.00');
        $rule = AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->action(UpdatePriceAction::CHAVE, [
                'price_type' => 'sale', 'operation' => 'percentual', 'amount' => '10',
            ])->create(['name' => 'teste automação']);
        app(UpdatePriceAction::class)->executar($rule, ['product_id' => $this->product->id]);
        $h = $sale->histories()->where('event_type', 'amount_changed')->sole();
        $this->assertSame('3.30', $h->new_amount);
        $this->assertSame('50.0000', $h->old_effective_margin_percentage);
        $this->assertSame('65.0000', $h->new_effective_margin_percentage);
        $this->assertSame('automação: teste automação', $h->reason);
        $this->assertNull($h->changed_by);
    }

    public function test_salvar_mesmo_valor_nao_cria_evento_duplicado(): void
    {
        $sale = $this->salvar('sale', '3.00');
        $this->salvar('sale', '3.00');
        $this->assertSame(1, $sale->histories()->count());
    }
}
