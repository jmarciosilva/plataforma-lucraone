<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Branches\Domain\BranchSegment;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\BranchContext;
use App\Modules\Tenancy\Application\BranchResolver;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * MS-02 — BranchResolver: estratégias de resolução e segurança.
 */
class BranchResolverTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private BranchResolver $resolver;

    private BranchContext $context;

    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->tenantContext = app(TenantContext::class);
        $this->tenantContext->set($this->tenant->id);

        $this->context = app(BranchContext::class);
        $this->context->clear();

        $this->resolver = app(BranchResolver::class);
    }

    protected function tearDown(): void
    {
        $this->tenantContext->clear();
        $this->context->clear();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Estratégia 1: sessão
    // ---------------------------------------------------------------

    public function test_resolve_por_sessao_valida(): void
    {
        $filial = Branch::factory()->forTenant($this->tenant)->active()->create();

        $request = $this->requisicaoComSessao($filial->id);

        $this->assertTrue($this->resolver->resolve($request));
        $this->assertTrue($this->context->resolved());
        $this->assertEquals($filial->id, $this->context->id());
    }

    public function test_sessao_de_outro_tenant_e_recusada(): void
    {
        $outroTenant = Tenant::factory()->active()->create();
        $filialOutro = Branch::factory()->forTenant($outroTenant)->active()->create();

        $request = $this->requisicaoComSessao($filialOutro->id);

        // Não resolve (filial não pertence ao tenant atual)
        $this->assertFalse($this->resolver->resolve($request));
        $this->assertFalse($this->context->resolved());
    }

    public function test_sessao_de_filial_inativa_e_recusada(): void
    {
        $filial = Branch::factory()->forTenant($this->tenant)->inactive()->create();

        $request = $this->requisicaoComSessao($filial->id);

        $this->assertFalse($this->resolver->resolve($request));
        $this->assertFalse($this->context->resolved());
        // A chave de sessão inválida é limpa
        $this->assertNull($request->session()->get(BranchResolver::SESSAO_FILIAL));
    }

    // ---------------------------------------------------------------
    // Estratégia 2: filial única
    // ---------------------------------------------------------------

    public function test_auto_resolve_quando_ha_filial_unica(): void
    {
        $filial = Branch::factory()->forTenant($this->tenant)->active()->create();

        $request = $this->requisicaoVazia();

        $this->assertTrue($this->resolver->resolve($request));
        $this->assertEquals($filial->id, $this->context->id());
    }

    public function test_nao_auto_resolve_com_multiplas_filiais(): void
    {
        Branch::factory()->forTenant($this->tenant)->active()->create();
        Branch::factory()->forTenant($this->tenant)->active()->create();

        $request = $this->requisicaoVazia();

        $this->assertFalse($this->resolver->resolve($request));
        $this->assertFalse($this->context->resolved());
    }

    // ---------------------------------------------------------------
    // Estratégia 3: sem resolução = visão consolidada (não é erro)
    // ---------------------------------------------------------------

    public function test_sem_filials_retorna_false_sem_excecao(): void
    {
        $request = $this->requisicaoVazia();

        $resultado = $this->resolver->resolve($request);

        $this->assertFalse($resultado);
        $this->assertFalse($this->context->resolved());
    }

    // ---------------------------------------------------------------
    // Sem tenant resolvido → recusar
    // ---------------------------------------------------------------

    public function test_sem_tenant_retorna_false(): void
    {
        $this->tenantContext->clear();
        Branch::factory()->forTenant($this->tenant)->active()->create();

        $request = $this->requisicaoVazia();

        $this->assertFalse($this->resolver->resolve($request));
    }

    // ---------------------------------------------------------------
    // Invalidação
    // ---------------------------------------------------------------

    public function test_invalidar_limpa_sessao_e_contexto(): void
    {
        $filial = Branch::factory()->forTenant($this->tenant)->active()->create();
        $request = $this->requisicaoComSessao($filial->id);
        $this->resolver->resolve($request);
        $this->assertTrue($this->context->resolved());

        $this->resolver->invalidar($request);

        $this->assertFalse($this->context->resolved());
        $this->assertNull($request->session()->get(BranchResolver::SESSAO_FILIAL));
    }

    // ---------------------------------------------------------------
    // Segmento acessível via BranchContext
    // ---------------------------------------------------------------

    public function test_contexto_expoe_segmento_da_filial(): void
    {
        $filial = Branch::factory()->forTenant($this->tenant)->restaurant()->create();
        $this->context->set($filial->id);

        $this->assertEquals(BranchSegment::RESTAURANT, $this->context->segment());
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------

    private function requisicaoComSessao(string $branchId): Request
    {
        $request = Request::create('/qualquer', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));
        $request->session()->put(BranchResolver::SESSAO_FILIAL, $branchId);

        return $request;
    }

    private function requisicaoVazia(): Request
    {
        $request = Request::create('/qualquer', 'GET');
        $request->setLaravelSession($this->app->make('session.store'));

        return $request;
    }
}
