<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\TokenAbility;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\TenantResolver;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SEC-03 — o resolver de estabelecimento precisa ser seguro por si.
 *
 * Hoje as rotas de negócio aplicam `['auth:sanctum', 'token.ability',
 * 'tenant']`, nessa ordem, então quando o resolver roda já existe um token
 * autenticado. A proteção funciona, mas é uma convenção: depende de todo
 * arquivo de rotas futuro repetir a ordem. Estes testes exercitam o resolver
 * **sem** a autenticação na frente, que é a única forma de provar que ele
 * recusa sozinho.
 *
 * Princípio: `X-Tenant-ID` é um pedido de contexto, não prova de autorização.
 */
class TenantResolverSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private TenantResolver $resolver;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->active()->create(['name' => 'Alfa']);
        $this->tenantB = Tenant::factory()->active()->create(['name' => 'Beta']);

        $this->resolver = app(TenantResolver::class);
        $this->context = app(TenantContext::class);
        $this->context->clear();
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------

    private function pessoaEm(Tenant ...$tenants): User
    {
        $usuario = User::factory()->create();

        foreach ($tenants as $tenant) {
            $usuario->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);
        }

        return $usuario->refresh();
    }

    /**
     * Requisição como o middleware a entrega: com o resolvedor de usuário já
     * preenchido pela autenticação, ou vazio quando não houve autenticação.
     */
    private function requisicao(?Authenticatable $usuario = null, ?string $header = null, ?string $naSessao = null): Request
    {
        $request = Request::create('/rota-qualquer', 'GET');
        $request->setUserResolver(fn () => $usuario);

        if ($header !== null) {
            $request->headers->set('X-Tenant-ID', $header);
        }

        if ($naSessao !== null) {
            $sessao = $this->app['session']->driver();
            $sessao->put(TenantResolver::SESSAO_TENANT, $naSessao);
            $request->setLaravelSession($sessao);
        }

        return $request;
    }

    private function assertNaoResolveu(bool $resultado): void
    {
        $this->assertFalse($resultado, 'o resolver não deveria ter resolvido estabelecimento');
        $this->assertFalse(
            $this->context->resolved(),
            'o contexto não pode ficar preenchido quando a resolução falha'
        );
    }

    // ---------------------------------------------------------------
    // O caso central: header sem identidade
    // ---------------------------------------------------------------

    public function test_header_sem_identidade_nao_resolve(): void
    {
        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao(header: $this->tenantA->id))
        );
    }

    public function test_sessao_sem_identidade_nao_resolve(): void
    {
        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao(naSessao: $this->tenantA->id))
        );
    }

    public function test_header_e_sessao_juntos_sem_identidade_nao_resolvem(): void
    {
        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao(
                header: $this->tenantA->id,
                naSessao: $this->tenantA->id,
            ))
        );
    }

    /**
     * A fronteira para sujeitos não-humanos.
     *
     * O resolver deriva estabelecimento de vínculo de pessoa. Uma identidade
     * autenticada que não seja `User` não tem vínculo e precisa de estratégia
     * própria — não pode herdar a do header. Até existir, é recusada.
     */
    public function test_identidade_que_nao_e_pessoa_nao_resolve(): void
    {
        $naoPessoa = new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 'sujeito-nao-humano';
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return null;
            }
        };

        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao($naoPessoa, header: $this->tenantA->id))
        );
    }

    // ---------------------------------------------------------------
    // Header com identidade
    // ---------------------------------------------------------------

    public function test_header_com_vinculo_ativo_resolve(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);

        $this->assertTrue(
            $this->resolver->resolve($this->requisicao($pessoa, header: $this->tenantA->id))
        );
        $this->assertSame($this->tenantA->id, $this->context->id());
    }

    public function test_header_de_estabelecimento_sem_vinculo_nao_resolve(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);

        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao($pessoa, header: $this->tenantB->id))
        );
    }

    public function test_header_com_vinculo_inativo_nao_resolve(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);
        $pessoa->joinTenant($this->tenantA->id, TenantUser::STATUS_INACTIVE);

        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao($pessoa->refresh(), header: $this->tenantA->id))
        );
    }

    public function test_header_com_conta_inativa_nao_resolve(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);
        $pessoa->forceFill(['status' => User::STATUS_INACTIVE])->save();

        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao($pessoa->refresh(), header: $this->tenantA->id))
        );
    }

    public function test_header_malformado_nao_resolve_nem_estoura(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);

        foreach (['nao-e-ulid', '../../etc/passwd', str_repeat('x', 500), '1 OR 1=1', ' '] as $lixo) {
            $this->context->clear();

            $this->assertNaoResolveu(
                $this->resolver->resolve($this->requisicao($pessoa, header: $lixo))
            );
        }
    }

    public function test_header_de_estabelecimento_inexistente_nao_resolve(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);

        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao($pessoa, header: (string) Str::ulid()))
        );
    }

    // ---------------------------------------------------------------
    // Sessão com identidade
    // ---------------------------------------------------------------

    public function test_sessao_com_vinculo_ativo_resolve(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA, $this->tenantB);

        $this->assertTrue(
            $this->resolver->resolve($this->requisicao($pessoa, naSessao: $this->tenantB->id))
        );
        $this->assertSame($this->tenantB->id, $this->context->id());
    }

    public function test_sessao_com_estabelecimento_sem_vinculo_cai_para_vinculo_unico(): void
    {
        // Comportamento preexistente preservado: a sessão inválida não aborta,
        // apenas não serve — e o vínculo único assume.
        $pessoa = $this->pessoaEm($this->tenantA);

        $this->assertTrue(
            $this->resolver->resolve($this->requisicao($pessoa, naSessao: $this->tenantB->id))
        );
        $this->assertSame($this->tenantA->id, $this->context->id());
    }

    // ---------------------------------------------------------------
    // Vínculos
    // ---------------------------------------------------------------

    public function test_vinculo_unico_resolve_sem_header_nem_sessao(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);

        $this->assertTrue($this->resolver->resolve($this->requisicao($pessoa)));
        $this->assertSame($this->tenantA->id, $this->context->id());
    }

    public function test_varios_vinculos_sem_escolha_nao_resolve(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA, $this->tenantB);

        $this->assertNaoResolveu($this->resolver->resolve($this->requisicao($pessoa)));
    }

    public function test_sem_vinculo_nenhum_nao_resolve(): void
    {
        $pessoa = User::factory()->create();

        $this->assertNaoResolveu($this->resolver->resolve($this->requisicao($pessoa)));
    }

    // ---------------------------------------------------------------
    // Contexto não vaza
    // ---------------------------------------------------------------

    public function test_falha_nao_preserva_contexto_de_resolucao_anterior(): void
    {
        $pessoa = $this->pessoaEm($this->tenantA);

        // Primeiro resolve com sucesso...
        $this->assertTrue(
            $this->resolver->resolve($this->requisicao($pessoa, header: $this->tenantA->id))
        );
        $this->assertSame($this->tenantA->id, $this->context->id());

        // ...e depois uma resolução sem identidade não pode deixar o
        // estabelecimento anterior em pé.
        $this->assertNaoResolveu(
            $this->resolver->resolve($this->requisicao(header: $this->tenantB->id))
        );
    }

    // ---------------------------------------------------------------
    // HTTP: o middleware sem autenticação na frente
    // ---------------------------------------------------------------

    public function test_rota_com_tenant_sem_autenticacao_nao_aceita_header(): void
    {
        // Rota registrada só neste teste: nenhuma rota de produção aplica
        // 'tenant' sem autenticação, e é exatamente essa combinação que
        // precisa ser provada segura.
        Route::middleware('tenant')->get('/_sec03/contexto', fn (TenantContext $context) => response()->json([
            'tenant' => $context->resolved() ? $context->id() : null,
        ]));

        $this->withHeader('X-Tenant-ID', $this->tenantA->id)
            ->getJson('/_sec03/contexto')
            ->assertForbidden();
    }

    public function test_rota_com_tenant_e_autenticacao_aceita_header_valido(): void
    {
        Route::middleware(['auth:sanctum', 'tenant'])->get('/_sec03/contexto-autenticado', fn (TenantContext $context) => response()->json([
            'tenant' => $context->resolved() ? $context->id() : null,
        ]));

        $pessoa = $this->pessoaEm($this->tenantA);
        $token = $pessoa->createToken('sec03')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenantA->id)
            ->getJson('/_sec03/contexto-autenticado')
            ->assertOk()
            ->assertJsonPath('tenant', $this->tenantA->id);
    }

    // ---------------------------------------------------------------
    // HTTP: rotas reais de negócio seguem funcionando
    // ---------------------------------------------------------------

    public function test_api_autenticada_com_estabelecimento_valido_responde(): void
    {
        [$pessoa, $token] = $this->pessoaAutorizada($this->tenantA);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenantA->id)
            ->getJson('/api/v1/products')
            ->assertOk();
    }

    public function test_api_autenticada_com_estabelecimento_alheio_recebe_403(): void
    {
        [$pessoa, $token] = $this->pessoaAutorizada($this->tenantA);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenantB->id)
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }

    public function test_api_com_vinculo_unico_dispensa_header(): void
    {
        [$pessoa, $token] = $this->pessoaAutorizada($this->tenantA);

        $this->withToken($token)->getJson('/api/v1/products')->assertOk();
    }

    public function test_api_com_vinculo_inativo_recebe_403(): void
    {
        [$pessoa, $token] = $this->pessoaAutorizada($this->tenantA);
        $pessoa->joinTenant($this->tenantA->id, TenantUser::STATUS_INACTIVE);

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenantA->id)
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }

    public function test_api_com_varios_vinculos_e_sem_header_recebe_403(): void
    {
        [$pessoa, $token] = $this->pessoaAutorizada($this->tenantA);
        $pessoa->joinTenant($this->tenantB->id, TenantUser::STATUS_ACTIVE);

        $this->withToken($token)->getJson('/api/v1/products')->assertForbidden();
    }

    /**
     * Pessoa com vínculo ativo, papel de gerente e token — para as rotas de
     * negócio, onde o SEC-01 exige permissão e o SEC-02 exige ability.
     *
     * @return array{0: User, 1: string}
     */
    private function pessoaAutorizada(Tenant $tenant): array
    {
        Company::factory()->forCurrentTenant($tenant->id)->active()->create();

        $provisionador = app(ProvisionarEstabelecimento::class);
        $provisionador->provisionarMatriz($tenant);

        $pessoa = User::factory()->forTenant($tenant)->create();
        $pessoa->assignRole('manager', $tenant->id);

        return [
            $pessoa->refresh(),
            $pessoa->createToken('sec03', TokenAbility::paraSessaoHumana())->plainTextToken,
        ];
    }
}
