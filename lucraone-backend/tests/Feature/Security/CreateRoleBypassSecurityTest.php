<?php

namespace Tests\Feature\Security;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Reporting\Infrastructure\Mail\SalesSummaryMail;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · coringa create-role nas funcionalidades de outros domínios.
 *
 * Baseline de caracterização: os testes descrevem o comportamento SEGURO e
 * falham enquanto create-role funcionar como bypass administrativo.
 *
 * A pessoa tem vínculo só no Tenant A e um papel cuja única permissão é
 * create-role. Não tem nenhuma permissão de módulo — nem view-sales ou
 * manage-sales, que a CustomerPolicy e o Gate view-reports também aceitam —,
 * então qualquer acesso concedido vem do coringa.
 *
 * Fora de escopo, de propósito: o uso de create-role no próprio domínio de
 * papéis e permissões, que a auditoria classificou como legítimo.
 */
#[Group('sec-04')]
#[Group('sec-04-create-role')]
class CreateRoleBypassSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenant;

    private User $criadorDePapeis;

    private Company $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['timezone' => 'UTC']);
        $this->provisionarPapeisPadrao();

        $papel = $this->papelPersonalizado($this->tenant, 'criador-de-papeis', ['create-role']);
        $this->criadorDePapeis = User::factory()->forTenant($this->tenant)->create([
            'email' => 'so.create-role@example.test',
        ]);
        $this->criadorDePapeis->assignRole($papel, $this->tenant->id);

        $this->empresa = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
    }

    // --------------------------------------------------------------- Leitura

    /**
     * Rota de listagem e a permissão que ela realmente exige.
     */
    public static function listagensDosModulos(): array
    {
        return [
            'produtos' => ['catalog.products.index', 'view-products'],
            'categorias' => ['catalog.categories.index', 'view-products'],
            'estoque' => ['inventory.index', 'view-inventory'],
            'pedidos' => ['sales.orders.index', 'view-sales'],
            'clientes' => ['sales.customers.index', 'view-customers'],
            'empresas' => ['companies.index', 'view-companies'],
            'automações' => ['automations.index', 'view-automations'],
        ];
    }

    #[DataProvider('listagensDosModulos')]
    public function test_create_role_nao_substitui_permissao_de_leitura_do_modulo(string $rota, string $permissaoNecessaria): void
    {
        $resposta = $this->actingAs($this->criadorDePapeis)->get(route($rota));

        $this->assertSame(
            403,
            $resposta->status(),
            "create-role não pode substituir {$permissaoNecessaria} (status recebido: {$resposta->status()})"
        );
    }

    public function test_create_role_nao_exibe_produto(): void
    {
        $produto = $this->produto();

        $this->actingAs($this->criadorDePapeis)
            ->get(route('catalog.products.show', $produto))
            ->assertForbidden();
    }

    public function test_create_role_nao_exibe_posicao_de_estoque(): void
    {
        $posicao = Inventory::factory()->forProduct($this->produto())->create(['quantity_on_hand' => 10]);

        $this->actingAs($this->criadorDePapeis)
            ->get(route('inventory.show', $posicao))
            ->assertForbidden();
    }

    // -------------------------------------------------------------- Products

    public function test_create_role_nao_cadastra_produto(): void
    {
        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('catalog.products.store'), [
            'company_id' => $this->empresa->id,
            'sku' => 'SKU-CORINGA-001',
            'name' => 'Produto Cadastrado Pelo Coringa',
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('products', ['sku' => 'SKU-CORINGA-001']);
        $resposta->assertForbidden();
    }

    public function test_create_role_nao_altera_produto(): void
    {
        $produto = $this->produto();

        $resposta = $this->actingAs($this->criadorDePapeis)->put(route('catalog.products.update', $produto), [
            'company_id' => $this->empresa->id,
            'sku' => $produto->sku,
            'name' => 'Produto Alterado Pelo Coringa',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('products', ['id' => $produto->id, 'name' => 'Produto Original']);
        $resposta->assertForbidden();
    }

    /**
     * Preço não tem Policy própria: o cadastro autoriza como alteração do produto.
     */
    public function test_create_role_nao_cadastra_preco_de_produto(): void
    {
        $produto = $this->produto();

        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('catalog.products.prices.store', $produto), [
            'currency' => 'BRL',
            'amount' => '19.90',
            'type' => 'sale',
        ]);

        $this->assertDatabaseMissing('prices', ['product_id' => $produto->id]);
        $resposta->assertForbidden();
    }

    public function test_create_role_nao_cadastra_categoria(): void
    {
        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('catalog.categories.store'), [
            'name' => 'Categoria Pelo Coringa',
            'slug' => 'categoria-pelo-coringa',
        ]);

        $this->assertDatabaseMissing('categories', ['slug' => 'categoria-pelo-coringa']);
        $resposta->assertForbidden();
    }

    // ------------------------------------------------------------- Inventory

    public function test_create_role_nao_movimenta_estoque(): void
    {
        $produto = $this->produto();

        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('inventory.adjust'), [
            'product_id' => (string) $produto->id,
            'company_id' => $this->empresa->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => '5',
            'reason' => 'entrada pelo coringa',
        ]);

        $this->assertDatabaseMissing('inventory_movements', ['product_id' => $produto->id]);
        $this->assertDatabaseMissing('inventories', ['product_id' => $produto->id]);
        $resposta->assertForbidden();
    }

    /**
     * StockLevelPolicy só tem rota de escrita no painel; não há leitura a cobrir.
     */
    public function test_create_role_nao_define_nivel_de_reposicao(): void
    {
        $produto = $this->produto();

        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('inventory.stock-levels.store'), [
            'product_id' => (string) $produto->id,
            'company_id' => $this->empresa->id,
            'min_qty' => '1',
            'max_qty' => '10',
            'reorder_point' => '2',
        ]);

        $this->assertDatabaseMissing('stock_levels', ['product_id' => $produto->id]);
        $resposta->assertForbidden();
    }

    // ----------------------------------------------------------------- Sales

    public function test_create_role_nao_cria_pedido(): void
    {
        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('sales.orders.store'), [
            'company_id' => $this->empresa->id,
            'customer_name' => 'Cliente Do Pedido Pelo Coringa',
        ]);

        $this->assertDatabaseMissing('orders', ['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseMissing('customers', ['name' => 'Cliente Do Pedido Pelo Coringa']);
        $resposta->assertForbidden();
    }

    public function test_create_role_nao_cadastra_cliente(): void
    {
        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('sales.customers.store'), [
            'company_id' => $this->empresa->id,
            'name' => 'Cliente Cadastrado Pelo Coringa',
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseMissing('customers', ['name' => 'Cliente Cadastrado Pelo Coringa']);
        $resposta->assertForbidden();
    }

    // ------------------------------------------------------------- Companies

    public function test_create_role_nao_cadastra_empresa(): void
    {
        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('companies.store'), [
            'legal_name' => 'Empresa Cadastrada Pelo Coringa LTDA',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseMissing('companies', ['legal_name' => 'Empresa Cadastrada Pelo Coringa LTDA']);
        $resposta->assertForbidden();
    }

    // ------------------------------------------------------------ Automation

    public function test_create_role_nao_liga_ou_desliga_automacao(): void
    {
        $regra = AutomationRule::factory()->forTenant($this->tenant->id)->create(['active' => true]);

        $resposta = $this->actingAs($this->criadorDePapeis)->post(route('automations.toggle', $regra));

        $this->assertDatabaseHas('automation_rules', ['id' => $regra->id, 'active' => true]);
        $resposta->assertForbidden();
    }

    // --------------------------------------------------- Reports (view-reports)

    public function test_create_role_nao_abre_relatorios_no_painel(): void
    {
        $this->actingAs($this->criadorDePapeis)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    /**
     * O Gate view-reports é o mesmo na API; a rota de relatórios já exige a
     * permissão por ReportPeriodRequest, então o 403 aqui mede só o coringa.
     */
    public function test_create_role_nao_consulta_relatorios_pela_api(): void
    {
        $token = $this->criadorDePapeis->createToken('teste')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/reports/sales')
            ->assertForbidden();
    }

    // ------------------------------------------------ Resumo de vendas por e-mail

    /**
     * Comando relatorios:enviar-resumo.
     *
     * O resumo sai num único e-mail por estabelecimento, com todos os
     * destinatários. O leitor de relatórios é controle: prova que o comando
     * enviou de fato, para que a ausência do criador de papéis signifique
     * exclusão, e não que nada foi enviado.
     */
    public function test_create_role_nao_torna_a_pessoa_destinataria_do_resumo_de_vendas(): void
    {
        Mail::fake();

        $leitor = User::factory()->forTenant($this->tenant)->create(['email' => 'leitor.relatorios@example.test']);
        $leitor->assignRole(
            $this->papelPersonalizado($this->tenant, 'leitor-de-relatorios', ['view-reports']),
            $this->tenant->id
        );

        $this->artisan('relatorios:enviar-resumo', ['--tenant' => $this->tenant->id])
            ->assertSuccessful();

        Mail::assertQueued(
            SalesSummaryMail::class,
            fn (SalesSummaryMail $mail) => $mail->hasTo('leitor.relatorios@example.test')
        );
        Mail::assertNotQueued(
            SalesSummaryMail::class,
            fn (SalesSummaryMail $mail) => $mail->hasTo('so.create-role@example.test')
        );
    }

    private function produto(): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->empresa->id,
            'sku' => 'SKU-A-0001',
            'name' => 'Produto Original',
            'status' => 'active',
        ]);
    }
}
