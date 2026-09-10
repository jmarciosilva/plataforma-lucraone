<?php

namespace Tests\Feature\Security;

use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · E7 — Policy avalia entidade e permissão em estabelecimentos diferentes.
 *
 * Baseline de caracterização: os testes descrevem o comportamento SEGURO e
 * falham enquanto o vetor existir.
 *
 * Cenário fixo: admin no Tenant A, viewer no Tenant B, Tenant A ativo, entidade
 * do Tenant B. O invariante do ROADMAP é que entidade, tenant da entidade e
 * tenant usado para consultar permissões representem o mesmo contexto. Por
 * isso até a leitura de B com A ativo precisa ser negada — ainda que, nos
 * módulos em que o viewer de B tem permissão de leitura, isso não conceda poder
 * novo. Formulários de edição, alterações e o módulo de papéis concedem.
 *
 * Aceita 403 ou 404: a convenção do projeto para entidade de outro
 * estabelecimento é não confirmar a existência dela.
 */
#[Group('sec-04')]
#[Group('sec-04-e7')]
class CrossTenantPolicyContextSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $pessoa;

    private Company $empresaA;

    private Company $empresaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->active()->create(['name' => 'Loja A']);
        $this->tenantB = Tenant::factory()->active()->create(['name' => 'Loja B']);
        $this->provisionarPapeisPadrao();

        $this->pessoa = $this->membroComPapel($this->tenantA, 'admin');
        $this->pessoa->joinTenant($this->tenantB->id, TenantUser::STATUS_ACTIVE);
        $this->pessoa->assignRole($this->papel($this->tenantB, 'viewer'), $this->tenantB->id);

        $this->empresaA = Company::factory()->forCurrentTenant($this->tenantA->id)->create();
        $this->empresaB = Company::factory()->forCurrentTenant($this->tenantB->id)->create([
            'legal_name' => 'Empresa B LTDA',
            'status' => 'ACTIVE',
        ]);
    }

    // ---------------------------------------------------------------- Product

    public function test_produto_de_b_nao_e_exibido_com_a_ativo(): void
    {
        $produtoB = $this->produtoDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('catalog.products.show', $produtoB));

        $this->assertNegadoPorIsolamento($resposta, 'exibir produto de B');
    }

    public function test_formulario_de_edicao_de_produto_de_b_nao_abre_com_a_ativo(): void
    {
        $produtoB = $this->produtoDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('catalog.products.edit', $produtoB));

        $this->assertNegadoPorIsolamento($resposta, 'abrir edição de produto de B');
    }

    /**
     * Teste canônico do problema de contexto das Policies.
     *
     * A empresa enviada é de A porque a validação confere company_id contra o
     * estabelecimento ativo: com a empresa de B, a validação barraria antes e
     * esconderia a falha de autorização.
     */
    public function test_produto_de_b_nao_e_alterado_com_permissao_de_a(): void
    {
        $produtoB = $this->produtoDeB();

        $resposta = $this->requisicaoComAAtivo('put', route('catalog.products.update', $produtoB), [
            'company_id' => $this->empresaA->id,
            'sku' => 'SKU-B-0001',
            'name' => 'Produto Alterado Com Permissao De A',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $produtoB->id,
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->empresaB->id,
            'name' => 'Produto Original B',
        ]);
        $this->assertNegadoPorIsolamento($resposta, 'alterar produto de B');
    }

    // --------------------------------------------------------------- Category

    public function test_categoria_de_b_nao_e_exibida_com_a_ativo(): void
    {
        $categoriaB = $this->categoriaDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('catalog.categories.show', $categoriaB));

        $this->assertNegadoPorIsolamento($resposta, 'exibir categoria de B');
    }

    public function test_formulario_de_edicao_de_categoria_de_b_nao_abre_com_a_ativo(): void
    {
        $categoriaB = $this->categoriaDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('catalog.categories.edit', $categoriaB));

        $this->assertNegadoPorIsolamento($resposta, 'abrir edição de categoria de B');
    }

    public function test_categoria_de_b_nao_e_arquivada_com_permissao_de_a(): void
    {
        $categoriaB = $this->categoriaDeB();

        $resposta = $this->requisicaoComAAtivo('delete', route('catalog.categories.destroy', $categoriaB));

        $this->assertNotSoftDeleted('categories', ['id' => $categoriaB->id]);
        $this->assertNegadoPorIsolamento($resposta, 'arquivar categoria de B');
    }

    // --------------------------------------------------------------- Customer

    public function test_cliente_de_b_nao_e_exibido_com_a_ativo(): void
    {
        $clienteB = $this->clienteDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('sales.customers.show', $clienteB));

        $this->assertNegadoPorIsolamento($resposta, 'exibir cliente de B');
    }

    public function test_formulario_de_edicao_de_cliente_de_b_nao_abre_com_a_ativo(): void
    {
        $clienteB = $this->clienteDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('sales.customers.edit', $clienteB));

        $this->assertNegadoPorIsolamento($resposta, 'abrir edição de cliente de B');
    }

    public function test_cliente_de_b_nao_e_alterado_com_permissao_de_a(): void
    {
        $clienteB = $this->clienteDeB();

        $resposta = $this->requisicaoComAAtivo('put', route('sales.customers.update', $clienteB), [
            'company_id' => $this->empresaA->id,
            'name' => 'Cliente Alterado Com Permissao De A',
            'status' => Customer::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $clienteB->id,
            'company_id' => $this->empresaB->id,
            'name' => 'Cliente Original B',
        ]);
        $this->assertNegadoPorIsolamento($resposta, 'alterar cliente de B');
    }

    // ------------------------------------------------------------------ Order

    public function test_pedido_de_b_nao_e_exibido_com_a_ativo(): void
    {
        $pedidoB = $this->pedidoDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('sales.orders.show', $pedidoB));

        $this->assertNegadoPorIsolamento($resposta, 'exibir pedido de B');
    }

    public function test_status_de_pedido_de_b_nao_muda_com_permissao_de_a(): void
    {
        $pedidoB = $this->pedidoDeB();

        $resposta = $this->requisicaoComAAtivo('put', route('sales.orders.status', $pedidoB), [
            'status' => Order::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $pedidoB->id,
            'status' => Order::STATUS_DRAFT,
        ]);
        $this->assertNegadoPorIsolamento($resposta, 'mudar status de pedido de B');
    }

    // ------------------------------------------------------------- Automation

    public function test_automacao_de_b_nao_e_exibida_com_a_ativo(): void
    {
        $regraB = $this->automacaoDeB();

        $resposta = $this->requisicaoComAAtivo('get', route('automations.show', $regraB));

        $this->assertNegadoPorIsolamento($resposta, 'exibir automação de B');
    }

    public function test_automacao_de_b_nao_e_desligada_com_permissao_de_a(): void
    {
        $regraB = $this->automacaoDeB();

        $resposta = $this->requisicaoComAAtivo('post', route('automations.toggle', $regraB));

        $this->assertDatabaseHas('automation_rules', [
            'id' => $regraB->id,
            'active' => true,
        ]);
        $this->assertNegadoPorIsolamento($resposta, 'desligar automação de B');
    }

    // ---------------------------------------------------------------- Company

    public function test_empresa_de_b_nao_e_exibida_com_a_ativo(): void
    {
        $resposta = $this->requisicaoComAAtivo('get', route('companies.show', $this->empresaB));

        $this->assertNegadoPorIsolamento($resposta, 'exibir empresa de B');
    }

    public function test_empresa_de_b_nao_e_desativada_com_permissao_de_a(): void
    {
        $resposta = $this->requisicaoComAAtivo('delete', route('companies.destroy', $this->empresaB));

        $this->assertDatabaseHas('companies', [
            'id' => $this->empresaB->id,
            'status' => 'ACTIVE',
        ]);
        $this->assertNegadoPorIsolamento($resposta, 'desativar empresa de B');
    }

    // ------------------------------------------------------------------- Role

    /**
     * Aqui a leitura já concede poder: o viewer de B não tem view-roles em B.
     */
    public function test_papel_de_b_nao_e_exibido_com_a_ativo(): void
    {
        $resposta = $this->requisicaoComAAtivo('get', route('roles.show', $this->papel($this->tenantB, 'user')));

        $this->assertNegadoPorIsolamento($resposta, 'exibir papel de B');
    }

    public function test_permissoes_de_papel_de_b_nao_mudam_com_permissao_de_a(): void
    {
        $papelB = $this->papel($this->tenantB, 'user');
        $antes = $this->permissoesDoPapel($papelB);

        $resposta = $this->requisicaoComAAtivo('post', route('roles.permissions.sync', $papelB), [
            'permissions' => [$this->permissao($this->tenantA, 'manage-products')->id],
        ]);

        $this->assertSame(
            $antes,
            $this->permissoesDoPapel($papelB),
            'o papel de B não pode receber linhas de permissão gravadas com o contexto de A'
        );
        $this->assertNegadoPorIsolamento($resposta, 'sincronizar permissões de papel de B');
    }

    // ---------------------------------------------------------------- Apoio

    /**
     * Escolhe o Tenant A pelo fluxo real e dispara uma única requisição.
     *
     * Uma requisição por teste de propósito: o TenantContext é singleton e
     * sobreviveria a uma requisição anterior, ativando o TenantScope já no
     * route binding — o que não acontece em produção, onde cada requisição
     * começa sem contexto. Com duas requisições o teste passaria sem provar nada.
     */
    private function requisicaoComAAtivo(string $metodo, string $url, array $dados = []): TestResponse
    {
        $this->actingAs($this->pessoa)
            ->post(route('estabelecimentos.definir'), ['tenant_id' => $this->tenantA->id])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse(
            app(TenantContext::class)->resolved(),
            'pré-condição: a requisição precisa começar sem estabelecimento resolvido, como em produção'
        );

        return $this->call(strtoupper($metodo), $url, $dados);
    }

    private function assertNegadoPorIsolamento(TestResponse $resposta, string $operacao): void
    {
        $this->assertContains(
            $resposta->status(),
            [403, 404],
            "{$operacao}: a permissão de A não pode autorizar entidade de B (status recebido: {$resposta->status()})"
        );
    }

    private function produtoDeB(): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'company_id' => $this->empresaB->id,
            'sku' => 'SKU-B-0001',
            'name' => 'Produto Original B',
            'status' => 'active',
        ]);
    }

    private function categoriaDeB(): Category
    {
        return Category::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Categoria B',
            'slug' => 'categoria-b',
        ]);
    }

    private function clienteDeB(): Customer
    {
        return Customer::factory()->forCompany($this->empresaB)->create([
            'name' => 'Cliente Original B',
        ]);
    }

    private function pedidoDeB(): Order
    {
        return Order::factory()->forCompany($this->empresaB)->create([
            'order_number' => 'PED-B-0001',
        ]);
    }

    private function automacaoDeB(): AutomationRule
    {
        return AutomationRule::factory()->forTenant($this->tenantB->id)->create([
            'name' => 'Regra De B',
        ]);
    }
}
