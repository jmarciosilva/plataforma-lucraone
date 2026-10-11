<?php

namespace Tests\Feature\Pdv;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorSessionContext;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Pdv\Http\Middleware\AuthenticateOperatorSession;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Base das suítes HTTP de autenticação e sessão do operador (Fase 5).
 *
 * Tudo passa pelas rotas reais, como no PDV-BE-05: o que está sob teste é o
 * contrato conjunto — máquina mais operador —, e chamar serviço direto não
 * provaria middleware, ability, freio, envelope de erro nem cabeçalho.
 *
 * O cenário montado aqui é o MÍNIMO suficiente para um login legítimo: uma loja
 * operacional, um caixa pareado, uma pessoa com vínculo ativo, com `access-pdv`
 * por papel real do estabelecimento e com atribuição à filial do caixa. Cada
 * teste tira exatamente uma dessas peças.
 */
abstract class PdvOperatorTestCase extends TestCase
{
    use RefreshDatabase;

    protected const SENHA = 'segredo-de-teste-123';

    /** IP público de documentação (RFC 5737), fora de qualquer faixa confiada. */
    protected const IP_CLIENTE = '198.51.100.42';

    protected Terminal $terminal;

    protected Tenant $tenant;

    protected Branch $branch;

    protected User $operador;

    protected string $maquina;

    /** Username operacional do operador montado no setUp. */
    protected string $username = 'caixa.teste';

    protected function setUp(): void
    {
        parent::setUp();

        $this->terminal = $this->terminalOperacional();
        $this->tenant = $this->terminal->tenant;
        $this->branch = Branch::withoutGlobalScopes()->findOrFail($this->terminal->branch_id);

        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->operador = $this->operadorAutorizado(username: $this->username);
        $this->maquina = $this->credencialDeMaquina($this->terminal);

        // Freio em store de array: nasce vazio em cada boot da aplicação, ou
        // seja, em cada teste. Se algum dia a suíte usar store persistente, a
        // contagem atravessaria testes e este ponto precisaria de limpeza.
        $this->assertSame('array', config('cache.default'));
    }

    /**
     * Um caixa ACTIVE, instalado, com estrutura operacional.
     */
    protected function terminalOperacional(?Branch $branch = null): Terminal
    {
        $terminal = $branch === null
            ? Terminal::factory()->create()
            : Terminal::factory()->forBranch($branch)->create();

        $terminal->tenant->update(['status' => 'ACTIVE', 'active' => true]);
        $terminal->update([
            'installation_id' => (string) Str::uuid(),
            'status' => Terminal::STATUS_ACTIVE,
        ]);

        return $terminal->fresh();
    }

    protected function credencialDeMaquina(Terminal $terminal): string
    {
        return app(IssueTerminalMachineCredential::class)->issue($terminal->fresh())->plainTextToken;
    }

    /**
     * Pessoa com tudo o que o login legítimo exige.
     *
     * Inclui o perfil de caixa: desde a gestão de operadores, o login resolve um
     * USERNAME dentro do estabelecimento, e sem perfil ativo não há o que
     * resolver. O username é derivado quando não informado, para que cada
     * operador de um mesmo teste tenha o seu sem o teste precisar inventar nomes.
     */
    protected function operadorAutorizado(?Tenant $tenant = null, ?Branch $branch = null, ?string $username = null): User
    {
        $tenant ??= $this->tenant;
        $branch ??= $this->branch;

        $operador = User::factory()->create(['password' => self::SENHA]);
        $operador->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);

        $this->concederPermissaoDePdv($operador, $tenant);
        $this->atribuirFilial($operador, $branch);
        $this->perfilDeOperador($operador, $tenant, $username ?? 'op'.Str::lower(Str::random(8)));

        return $operador->fresh();
    }

    /**
     * Perfil de caixa ativo, com username normalizado.
     */
    protected function perfilDeOperador(User $operador, ?Tenant $tenant = null, string $username = 'caixa.teste'): PdvOperatorProfile
    {
        $tenant ??= $this->tenant;

        $perfil = PdvOperatorProfile::withoutGlobalScopes()->firstOrNew([
            'tenant_id' => $tenant->id,
            'user_id' => $operador->id,
        ]);

        $perfil->id ??= (string) Str::ulid();
        $perfil->status = PdvOperatorProfile::STATUS_ACTIVE;
        $perfil->definirUsername($username);
        $perfil->save();

        return $perfil->fresh();
    }

    /**
     * Dá `access-pdv` por papel REAL do estabelecimento.
     *
     * A permissão é órfã nos papéis padrão de propósito, então o cenário cria um
     * papel específico de caixa — que é exatamente o que o administrador faria.
     * Atribuir a permissão por fora do RBAC faria o fixture divergir de produção.
     */
    protected function concederPermissaoDePdv(User $operador, ?Tenant $tenant = null): void
    {
        $tenant ??= $this->tenant;

        $papel = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'pdv-operator'],
            ['id' => (string) Str::ulid(), 'description' => 'Operador de caixa']
        );

        $papel->grantPermission(Permission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', OperatorPermission::ACESSO)
            ->firstOrFail());

        $operador->assignRole($papel, $tenant->id);
    }

    protected function atribuirFilial(User $operador, ?Branch $branch = null, ?User $ator = null): PdvOperatorBranchAssignment
    {
        $branch ??= $this->branch;

        $atribuicao = PdvOperatorBranchAssignment::withoutGlobalScopes()->firstOrNew([
            'tenant_id' => $branch->tenant_id,
            'user_id' => $operador->id,
            'branch_id' => $branch->id,
        ]);

        $atribuicao->forceFill([
            'id' => $atribuicao->id ?? (string) Str::ulid(),
            'status' => PdvOperatorBranchAssignment::STATUS_ACTIVE,
            'granted_by' => $ator?->id,
            'revoked_at' => null,
        ])->save();

        return $atribuicao->fresh();
    }

    /**
     * Reproduz o isolamento que cada requisição tem em produção.
     *
     * Dois estados sobrevivem entre chamadas dentro de um mesmo teste e não
     * sobrevivem entre requisições reais:
     *
     *   - o RequestGuard vive no AuthManager e guarda o sujeito já resolvido,
     *     então sem `forgetGuards()` a segunda chamada devolveria o resultado
     *     da primeira sem reavaliar token nem política;
     *   - os contextos por requisição (estabelecimento, terminal, operador) são
     *     singletons do container. Em produção eles nascem vazios a cada
     *     requisição e são preenchidos pelos middlewares. No teste, um contexto
     *     remanescente faria o TenantScope filtrar a consulta do tokenable pelo
     *     estabelecimento ANTERIOR — e um caixa de outra loja apareceria como
     *     token inválido, escondendo o comportamento real.
     *
     * Os cabeçalhos padrão do TestCase também são zerados: `withHeaders()` os
     * acumula para as requisições seguintes do mesmo teste. Sem o flush, um
     * `X-Tenant-ID` posto numa chamada administrativa vazaria para a chamada de
     * máquina seguinte — que o recusa, corretamente, e o teste passaria a medir
     * o cabeçalho vazado em vez do que ele quer provar.
     */
    protected function novaRequisicao(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        app(TenantContext::class)->clear();
        app(TerminalContext::class)->clear();
        app(OperatorSessionContext::class)->clear();
    }

    /**
     * POST de login no endpoint real.
     */
    protected function login(?string $maquina = null, array $payload = [], array $cabecalhos = []): TestResponse
    {
        $this->novaRequisicao();

        $corpo = $payload === []
            ? ['username' => $this->username, 'password' => self::SENHA]
            : $payload;

        return $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($maquina ?? $this->maquina)
            ->withHeaders($cabecalhos)
            ->postJson('/api/v1/pdv/operator-sessions', $corpo);
    }

    /**
     * Credencial da sessão operacional de um login bem-sucedido.
     */
    protected function abrirSessao(?string $maquina = null): string
    {
        return $this->login($maquina)->assertSuccessful()->json('data.credential.access_token');
    }

    /**
     * Requisição com as DUAS identidades.
     */
    protected function comSessao(
        string $metodo,
        string $rota,
        string $credencial,
        ?string $maquina = null,
        array $cabecalhos = [],
        array $payload = [],
    ): TestResponse {
        $this->novaRequisicao();

        return $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($maquina ?? $this->maquina)
            ->withHeaders([AuthenticateOperatorSession::HEADER => $credencial] + $cabecalhos)
            ->json($metodo, $rota, $payload);
    }

    protected function consultarSessao(string $credencial, ?string $maquina = null): TestResponse
    {
        return $this->comSessao('GET', '/api/v1/pdv/operator-session', $credencial, $maquina);
    }

    protected function revalidar(string $credencial, ?string $maquina = null): TestResponse
    {
        return $this->comSessao('POST', '/api/v1/pdv/operator-session/revalidate', $credencial, $maquina);
    }

    protected function logout(string $credencial, ?string $maquina = null): TestResponse
    {
        return $this->comSessao('DELETE', '/api/v1/pdv/operator-session', $credencial, $maquina);
    }

    /**
     * Promove uma credencial de máquina nova pelo fluxo real de rotação.
     */
    protected function rotacionarMaquina(): string
    {
        $operacao = (string) Str::uuid();
        $segredo = bin2hex(random_bytes(32));

        $this->novaRequisicao();
        $candidata = $this->withToken($this->maquina)
            ->postJson('/api/v1/pdv/terminal/credential/rotations', [
                'operation_id' => $operacao,
                'delivery_secret' => $segredo,
            ])->assertOk()->json('data.credential.access_token');

        $this->novaRequisicao();
        $this->withToken($candidata)
            ->postJson('/api/v1/pdv/terminal/credential/confirm', ['operation_id' => $operacao])
            ->assertOk();

        return $candidata;
    }

    /**
     * O envelope de erro público, com request_id igual ao cabeçalho e sem
     * nenhum detalhe interno.
     */
    protected function assertErroPdv(TestResponse $resposta, int $status, string $code): void
    {
        $resposta->assertStatus($status)
            ->assertJsonPath('error.code', $code)
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id']]);

        $cabecalho = $resposta->headers->get('X-Request-ID');
        $this->assertNotEmpty($cabecalho, 'Erro do PDV precisa de X-Request-ID no cabeçalho.');
        $resposta->assertJsonPath('error.request_id', $cabecalho);

        $corpo = $resposta->getContent();
        foreach (['SQLSTATE', 'vendor/', '/app/', 'Exception', 'stack', self::SENHA] as $proibido) {
            $this->assertStringNotContainsString($proibido, $corpo);
        }
    }
}
