<?php

namespace Tests\Feature\Security;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SEC-02 — autenticação da API.
 *
 * Cobre os quatro defeitos comprovados na auditoria: login sem freio, token
 * `['*']`, token eterno e diferença de caminho entre e-mail inexistente e
 * senha errada. Mais a revogação: o que o logout alcança e o que acontece com
 * o token de uma conta desativada.
 *
 * O SEC-01 fica de fora: aqui nada depende de permissão de papel.
 */
class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SENHA = 'segredo-de-teste-123';

    private Tenant $tenant;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();

        $provisionador = app(ProvisionarEstabelecimento::class);
        $provisionador->provisionarMatriz($this->tenant);

        $this->usuario = User::factory()->forTenant($this->tenant)->create([
            'email' => 'pessoa@exemplo.test',
            'password' => self::SENHA,
        ]);
        $this->usuario->assignRole('manager', $this->tenant->id);

        // O limiter vive no cache; sem limpar, a contagem de um teste
        // atravessaria para o seguinte.
        RateLimiter::clear($this->chaveEsperada('pessoa@exemplo.test'));
    }

    private function chaveEsperada(string $email): string
    {
        return mb_strtolower($email).'|127.0.0.1';
    }

    /**
     * Esquece os guards resolvidos.
     *
     * Dentro de um mesmo teste o guard `sanctum` guarda o usuário já
     * resolvido, então uma segunda requisição reaproveitaria a autenticação da
     * primeira e um token revogado ou expirado pareceria continuar valendo. É
     * artefato do cliente de teste, não do aplicativo.
     */
    private function esquecerGuards(): void
    {
        $this->app->get('auth')->forgetGuards();
    }

    private function login(string $email, string $senha): TestResponse
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $senha,
        ]);
    }

    // ---------------------------------------------------------------
    // Contrato preservado
    // ---------------------------------------------------------------

    public function test_login_valido_mantem_o_contrato_atual(): void
    {
        $resposta = $this->login('pessoa@exemplo.test', self::SENHA)->assertOk();

        $resposta->assertJsonStructure(['message', 'token', 'user', 'tenants']);
        $this->assertSame('Login realizado com sucesso', $resposta->json('message'));
        $this->assertSame('pessoa@exemplo.test', $resposta->json('user.email'));
        $this->assertNotEmpty($resposta->json('token'));
        $this->assertCount(1, $resposta->json('tenants'));
    }

    public function test_login_valido_passa_a_expor_expiracao_e_abilities(): void
    {
        $resposta = $this->login('pessoa@exemplo.test', self::SENHA)->assertOk();

        $this->assertNotNull($resposta->json('expires_at'), 'login deve informar quando o token expira');
        // ISO-8601 em UTC
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/',
            $resposta->json('expires_at')
        );

        $this->assertSame(
            ['business:read', 'business:write'],
            $resposta->json('abilities')
        );
    }

    // ---------------------------------------------------------------
    // Abilities — o token deixa de ser onipotente
    // ---------------------------------------------------------------

    public function test_token_emitido_nao_tem_ability_coringa(): void
    {
        $this->login('pessoa@exemplo.test', self::SENHA)->assertOk();

        $token = $this->usuario->tokens()->firstOrFail();

        $this->assertNotContains('*', $token->abilities, 'token não pode nascer com ability coringa');
        $this->assertSame(['business:read', 'business:write'], $token->abilities);
    }

    public function test_token_sem_ability_de_escrita_nao_escreve(): void
    {
        $somenteLeitura = $this->usuario->createToken('leitura', ['business:read'])->plainTextToken;

        $this->withToken($somenteLeitura)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertOk();

        $this->withToken($somenteLeitura)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/products', [
                'company_id' => Company::query()->firstOrFail()->id,
                'sku' => 'SEC02-001',
                'name' => 'Produto',
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['sku' => 'SEC02-001']);
    }

    public function test_token_sem_ability_de_leitura_nao_le(): void
    {
        $semNada = $this->usuario->createToken('nada', ['outra:coisa'])->plainTextToken;

        $this->withToken($semNada)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Expiração
    // ---------------------------------------------------------------

    public function test_expiracao_esta_configurada(): void
    {
        $this->assertNotNull(config('sanctum.expiration'), 'token sem expiração vale para sempre');
        $this->assertSame(720, config('sanctum.expiration'));
    }

    public function test_token_vale_antes_do_prazo(): void
    {
        $token = $this->login('pessoa@exemplo.test', self::SENHA)->json('token');

        $this->travel(11)->hours();
        $this->esquecerGuards();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertOk();
    }

    public function test_token_nao_vale_depois_do_prazo(): void
    {
        $token = $this->login('pessoa@exemplo.test', self::SENHA)->json('token');

        $this->travel(13)->hours();
        $this->esquecerGuards();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Throttle
    // ---------------------------------------------------------------

    public function test_excesso_de_tentativas_devolve_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('pessoa@exemplo.test', 'senha-errada')->assertUnauthorized();
        }

        $bloqueado = $this->login('pessoa@exemplo.test', 'senha-errada');

        $bloqueado->assertStatus(429);
        $bloqueado->assertHeader('Retry-After');
        $this->assertIsString($bloqueado->json('message'));
    }

    public function test_bloqueio_tambem_vale_para_a_senha_correta(): void
    {
        // Senão o freio seria inútil: bastaria acertar na tentativa seguinte.
        for ($i = 0; $i < 5; $i++) {
            $this->login('pessoa@exemplo.test', 'senha-errada')->assertUnauthorized();
        }

        $this->login('pessoa@exemplo.test', self::SENHA)->assertStatus(429);
    }

    public function test_bloqueio_expira_com_o_tempo(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('pessoa@exemplo.test', 'senha-errada')->assertUnauthorized();
        }
        $this->login('pessoa@exemplo.test', self::SENHA)->assertStatus(429);

        $this->travel(61)->seconds();

        $this->login('pessoa@exemplo.test', self::SENHA)->assertOk();
    }

    public function test_login_valido_zera_o_contador(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->login('pessoa@exemplo.test', 'senha-errada')->assertUnauthorized();
        }

        $this->login('pessoa@exemplo.test', self::SENHA)->assertOk();

        // Se o contador não tivesse sido limpo, estas quatro novas falhas
        // estourariam o limite antes da quinta.
        for ($i = 0; $i < 4; $i++) {
            $this->login('pessoa@exemplo.test', 'senha-errada')->assertUnauthorized();
        }
    }

    public function test_bloqueio_de_uma_conta_nao_bloqueia_outra(): void
    {
        $outro = User::factory()->forTenant($this->tenant)->create([
            'email' => 'outra@exemplo.test',
            'password' => self::SENHA,
        ]);
        $outro->assignRole('manager', $this->tenant->id);

        for ($i = 0; $i < 5; $i++) {
            $this->login('pessoa@exemplo.test', 'senha-errada')->assertUnauthorized();
        }
        $this->login('pessoa@exemplo.test', self::SENHA)->assertStatus(429);

        $this->login('outra@exemplo.test', self::SENHA)->assertOk();
    }

    // ---------------------------------------------------------------
    // Enumeração por tempo
    // ---------------------------------------------------------------

    public function test_email_inexistente_tambem_verifica_hash(): void
    {
        // Prova de comportamento, não de cronômetro: o caminho do e-mail
        // inexistente precisa executar a mesma verificação de hash, senão a
        // diferença de tempo denuncia quais contas existem.
        Hash::spy();

        $this->login('ninguem@exemplo.test', 'qualquer-senha')->assertUnauthorized();

        Hash::shouldHaveReceived('check')->once();
    }

    public function test_senha_errada_verifica_hash(): void
    {
        Hash::spy();

        $this->login('pessoa@exemplo.test', 'senha-errada')->assertUnauthorized();

        Hash::shouldHaveReceived('check')->once();
    }

    public function test_email_inexistente_e_senha_errada_sao_indistinguiveis(): void
    {
        $inexistente = $this->login('ninguem@exemplo.test', 'qualquer-senha');
        RateLimiter::clear($this->chaveEsperada('pessoa@exemplo.test'));
        $senhaErrada = $this->login('pessoa@exemplo.test', 'senha-errada');

        $this->assertSame(401, $inexistente->status());
        $this->assertSame(401, $senhaErrada->status());
        $this->assertSame($inexistente->json(), $senhaErrada->json());
        $this->assertSame(['message' => 'Credenciais inválidas'], $senhaErrada->json());
    }

    public function test_resposta_de_falha_nao_vaza_contagem_nem_hash(): void
    {
        $corpo = $this->login('pessoa@exemplo.test', 'senha-errada')->content();

        foreach (['$2y$', 'bcrypt', 'attempts', 'tentativas', self::SENHA] as $vazamento) {
            $this->assertStringNotContainsString($vazamento, $corpo);
        }
    }

    // ---------------------------------------------------------------
    // Status da conta — comportamento atual preservado
    // ---------------------------------------------------------------

    public function test_conta_inativa_continua_recebendo_403(): void
    {
        $inativo = User::factory()->forTenant($this->tenant)->create([
            'email' => 'inativo@exemplo.test',
            'password' => self::SENHA,
            'status' => User::STATUS_INACTIVE,
        ]);
        $inativo->assignRole('manager', $this->tenant->id);

        $this->login('inativo@exemplo.test', self::SENHA)
            ->assertStatus(403)
            ->assertJson(['message' => 'Conta inativa']);
    }

    // ---------------------------------------------------------------
    // Revogação
    // ---------------------------------------------------------------

    public function test_logout_revoga_o_token_atual_e_preserva_os_outros(): void
    {
        $tokenA = $this->usuario->createToken('a', ['business:read', 'business:write'])->plainTextToken;
        $tokenB = $this->usuario->createToken('b', ['business:read', 'business:write'])->plainTextToken;

        $this->assertSame(2, $this->usuario->tokens()->count());

        $this->withToken($tokenA)->postJson('/api/auth/logout')->assertOk();

        // Prova no banco: só o token usado no logout foi apagado.
        $this->assertSame(1, $this->usuario->tokens()->count());
        $this->assertSame('b', $this->usuario->tokens()->firstOrFail()->name);

        $this->esquecerGuards();
        $this->withToken($tokenA)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertUnauthorized();

        $this->esquecerGuards();
        $this->withToken($tokenB)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertOk();
    }

    public function test_token_de_conta_desativada_para_de_valer(): void
    {
        $token = $this->login('pessoa@exemplo.test', self::SENHA)->json('token');

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertOk();

        $this->usuario->forceFill(['status' => User::STATUS_INACTIVE])->save();
        $this->esquecerGuards();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertUnauthorized();
    }

    public function test_token_de_vinculo_desativado_para_de_operar_no_estabelecimento(): void
    {
        $token = $this->login('pessoa@exemplo.test', self::SENHA)->json('token');

        $this->usuario->joinTenant($this->tenant->id, TenantUser::STATUS_INACTIVE);
        $this->esquecerGuards();

        // Conta segue ativa, então o token continua autenticando; o que cai é
        // o acesso ao estabelecimento, barrado pelo middleware de tenant.
        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }
}
