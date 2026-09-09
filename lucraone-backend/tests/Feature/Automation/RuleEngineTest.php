<?php

namespace Tests\Feature\Automation;

use App\Modules\Automation\Application\Actions\CreateNotificationAction;
use App\Modules\Automation\Application\Actions\SendEmailAction;
use App\Modules\Automation\Application\Actions\UpdatePriceAction;
use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Automation\Domain\Operator;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Automation\Infrastructure\Mail\AutomationAlertMail;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Application\InventoryAdjustmentService;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * F2.5 — Advanced Automation (motor de regras)
 *
 * A fila roda em `sync` nos testes, então o gatilho percorre o caminho inteiro:
 * evento → listener → motor → ação.
 */
class RuleEngineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->outroTenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();

        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_produto_criado_dispara_regra_e_cria_aviso(): void
    {
        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->action(CreateNotificationAction::CHAVE, [
                'title' => 'produto novo: {nome}',
                'message' => 'sku {sku} foi cadastrado.',
                'level' => Notification::NIVEL_INFO,
            ])
            ->create(['name' => 'avisar produto novo']);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Café Novo',
            'sku' => 'AUT-001',
        ]);

        $aviso = Notification::withoutGlobalScopes()->firstOrFail();

        // Os placeholders foram trocados pelos dados do gatilho
        $this->assertSame('produto novo: Café Novo', $aviso->title);
        $this->assertSame('sku AUT-001 foi cadastrado.', $aviso->message);

        $this->assertDatabaseHas('automation_logs', [
            'tenant_id' => $this->tenant->id,
            'trigger' => TriggerCatalog::PRODUTO_CRIADO,
            'result' => AutomationLog::EXECUTADO,
        ]);
    }

    public function test_condicao_nao_satisfeita_registra_regra_ignorada(): void
    {
        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->conditions([
                ['campo' => 'nome', 'operador' => Operator::CONTEM, 'valor' => 'vinho'],
            ])
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Café Comum',
        ]);

        $this->assertSame(0, Notification::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('automation_logs', [
            'result' => AutomationLog::IGNORADO,
            'message' => 'condições da regra não foram satisfeitas.',
        ]);
    }

    public function test_condicao_satisfeita_executa(): void
    {
        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->conditions([
                ['campo' => 'nome', 'operador' => Operator::CONTEM, 'valor' => 'vinho'],
            ])
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Vinho Tinto Reserva',
        ]);

        $this->assertSame(1, Notification::withoutGlobalScopes()->count());
    }

    public function test_regra_inativa_nao_dispara(): void
    {
        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->inactive()
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);

        $this->assertSame(0, Notification::withoutGlobalScopes()->count());
        $this->assertSame(0, AutomationLog::withoutGlobalScopes()->count());
    }

    public function test_regra_de_outro_tenant_nao_ve_o_gatilho(): void
    {
        AutomationRule::factory()
            ->forTenant($this->outroTenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);

        $this->assertSame(0, Notification::withoutGlobalScopes()->count());
    }

    public function test_estoque_baixo_dispara_apenas_na_travessia(): void
    {
        $produto = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Arroz Controlado',
        ]);
        Inventory::factory()->forProduct($produto)->create(['quantity_on_hand' => 20, 'reserved' => 0]);
        StockLevel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $produto->id,
            'company_id' => $this->company->id,
            'min_qty' => 2,
            'reorder_point' => 10,
            'max_qty' => 100,
        ]);

        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::ESTOQUE_BAIXO)
            ->create();

        $servico = app(InventoryAdjustmentService::class);

        // 20 → 15: ainda acima do ponto, não anuncia
        $servico->adjust($produto, $this->company->id, InventoryMovement::TYPE_OUT, 5, 'venda');
        $this->assertSame(0, Notification::withoutGlobalScopes()->count());

        // 15 → 8: cruzou o ponto de reposição
        $servico->adjust($produto, $this->company->id, InventoryMovement::TYPE_OUT, 7, 'venda');
        $this->assertSame(1, Notification::withoutGlobalScopes()->count());

        // 8 → 6: continua baixo, mas não cruzou de novo — não repete o aviso
        $servico->adjust($produto, $this->company->id, InventoryMovement::TYPE_OUT, 2, 'venda');
        $this->assertSame(1, Notification::withoutGlobalScopes()->count());
    }

    public function test_acao_de_email_envia_para_destinatarios(): void
    {
        Mail::fake();

        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->action(SendEmailAction::CHAVE, [
                'recipients' => 'compras@casa.test, gerencia@casa.test',
                'subject' => 'novo produto: {nome}',
                'message' => 'confira o cadastro.',
            ])
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Queijo Curado',
        ]);

        Mail::assertQueued(AutomationAlertMail::class, function (AutomationAlertMail $mail) {
            return $mail->assunto === 'novo produto: Queijo Curado'
                && $mail->hasTo('compras@casa.test')
                && $mail->hasTo('gerencia@casa.test');
        });
    }

    public function test_email_sem_destinatario_valido_registra_falha(): void
    {
        Mail::fake();

        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->action(SendEmailAction::CHAVE, [
                'recipients' => 'isso-nao-e-email',
                'subject' => 'teste',
            ])
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);

        Mail::assertNothingQueued();
        $this->assertDatabaseHas('automation_logs', [
            'result' => AutomationLog::FALHOU,
            'message' => 'nenhum destinatário válido configurado na regra.',
        ]);
    }

    public function test_atualizar_preco_grava_historico(): void
    {
        $produto = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Item Reprecificado',
        ]);
        $preco = Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $produto->id,
            'type' => Price::TYPE_SALE,
            'currency' => 'BRL',
            'amount' => 100.00,
        ]);

        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::ESTOQUE_BAIXO)
            ->action(UpdatePriceAction::CHAVE, [
                'price_type' => Price::TYPE_SALE,
                'operation' => UpdatePriceAction::OPERACAO_PERCENTUAL,
                'amount' => 10,
            ])
            ->create(['name' => 'subir preco quando faltar']);

        Inventory::factory()->forProduct($produto)->create(['quantity_on_hand' => 20, 'reserved' => 0]);
        StockLevel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $produto->id,
            'company_id' => $this->company->id,
            'min_qty' => 2,
            'reorder_point' => 10,
            'max_qty' => 100,
        ]);

        app(InventoryAdjustmentService::class)
            ->adjust($produto, $this->company->id, InventoryMovement::TYPE_OUT, 15, 'venda');

        $this->assertDatabaseHas('prices', ['id' => $preco->id, 'amount' => '110.00']);
        $this->assertDatabaseHas('price_histories', [
            'price_id' => $preco->id,
            'old_amount' => '100.00',
            'new_amount' => '110.00',
            'reason' => 'automação: subir preco quando faltar',
        ]);
    }

    public function test_variacao_de_preco_acima_do_limite_e_recusada(): void
    {
        $produto = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);
        $preco = Price::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $produto->id,
            'type' => Price::TYPE_SALE,
            'currency' => 'BRL',
            'amount' => 100.00,
        ]);

        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->action(UpdatePriceAction::CHAVE, [
                'price_type' => Price::TYPE_SALE,
                'operation' => UpdatePriceAction::OPERACAO_PERCENTUAL,
                'amount' => -90,
            ])
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);

        // O preço do produto original não foi tocado
        $this->assertDatabaseHas('prices', ['id' => $preco->id, 'amount' => '100.00']);
        $this->assertDatabaseHas('automation_logs', ['result' => AutomationLog::FALHOU]);
    }

    public function test_regra_que_falha_nao_impede_as_outras(): void
    {
        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->action(UpdatePriceAction::CHAVE, [
                'price_type' => Price::TYPE_SALE,
                'operation' => UpdatePriceAction::OPERACAO_DEFINIR,
                'amount' => 10,
            ])
            ->create(['name' => 'regra quebrada']);

        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->create(['name' => 'regra boa']);

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);

        // A primeira falha (produto sem preço cadastrado), a segunda executa
        $this->assertSame(1, AutomationLog::withoutGlobalScopes()->where('result', AutomationLog::FALHOU)->count());
        $this->assertSame(1, AutomationLog::withoutGlobalScopes()->where('result', AutomationLog::EXECUTADO)->count());
        $this->assertSame(1, Notification::withoutGlobalScopes()->count());
    }

    public function test_campo_fora_do_catalogo_nao_e_avaliado(): void
    {
        AutomationRule::factory()
            ->forTenant($this->tenant->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->conditions([
                // `tenant_id` não é campo declarado pelo gatilho
                ['campo' => 'tenant_id', 'operador' => Operator::IGUAL, 'valor' => $this->tenant->id],
            ])
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);

        $this->assertSame(0, Notification::withoutGlobalScopes()->count());
        $this->assertDatabaseHas('automation_logs', ['result' => AutomationLog::IGNORADO]);
    }
}
