<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Identificador vazio nunca identifica ninguém.
 *
 * Caracterização de um buraco real, encontrado ao tornar `users.email`
 * opcional para operadores de caixa.
 *
 * `EloquentUserProvider::retrieveByCredentials()` monta a consulta com
 * `$query->where($chave, $valor)`. No query builder, `where('email', null)` vira
 * `email IS NULL`. Com e-mail obrigatório isso era inofensivo — nenhuma linha
 * tinha NULL. Com e-mail opcional, passaria a casar com o conjunto exato dos
 * operadores de caixa, e bastaria acertar a senha de um deles.
 *
 * Os FormRequests de login já exigiam e-mail e barravam esse corpo, mas a
 * garantia não pode morar numa validação de tela: qualquer caminho novo de
 * autenticação — comando, job, endpoint futuro — herdaria o buraco. Estes
 * testes verificam o PROVIDER, que é onde a correção mora.
 */
#[Group('sec-identidade')]
class NullIdentifierAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $comEmail;

    private User $semEmail;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->active()->create();

        $this->comEmail = User::factory()->create([
            'email' => 'gerente@loja.test',
            'password' => 'senha-do-gerente-1',
        ]);
        $this->comEmail->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);

        // Operador de caixa: existe, tem senha, e não tem e-mail.
        $this->semEmail = User::factory()->create([
            'email' => null,
            'password' => 'senha-do-caixa-123',
        ]);
        $this->semEmail->joinTenant($tenant->id, TenantUser::STATUS_ACTIVE);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function identificadoresVazios(): array
    {
        return [
            'nulo' => [null],
            'string vazia' => [''],
        ];
    }

    #[DataProvider('identificadoresVazios')]
    public function test_identificador_vazio_nao_autentica_nem_com_a_senha_certa(mixed $email): void
    {
        $this->assertFalse(Auth::guard('web')->attempt([
            'email' => $email,
            'password' => 'senha-do-caixa-123',
        ]));

        $this->assertGuest();
    }

    #[DataProvider('identificadoresVazios')]
    public function test_identificador_vazio_nao_resolve_pessoa_no_provider(mixed $email): void
    {
        $encontrado = Auth::guard('web')->getProvider()->retrieveByCredentials([
            'email' => $email,
            'password' => 'senha-do-caixa-123',
        ]);

        $this->assertNull($encontrado);
    }

    /**
     * Guarda contra teste vacuoso: a recusa é do valor vazio, não do mecanismo.
     */
    public function test_identificador_valido_continua_autenticando(): void
    {
        $this->assertTrue(Auth::guard('web')->attempt([
            'email' => 'gerente@loja.test',
            'password' => 'senha-do-gerente-1',
        ]));

        $this->assertAuthenticatedAs($this->comEmail);
    }

    public function test_senha_errada_continua_sendo_recusada(): void
    {
        $this->assertFalse(Auth::guard('web')->attempt([
            'email' => 'gerente@loja.test',
            'password' => 'senha-errada',
        ]));
    }

    /**
     * O login web continua recusando o corpo sem e-mail na camada de
     * validação também — as duas defesas coexistem.
     */
    public function test_login_web_continua_exigindo_email(): void
    {
        $this->post(route('login'), ['email' => '', 'password' => 'senha-do-caixa-123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * E o login humano da API também.
     */
    public function test_login_da_api_continua_exigindo_email(): void
    {
        $this->postJson('/api/auth/login', ['password' => 'senha-do-caixa-123'])
            ->assertStatus(422);

        $this->postJson('/api/auth/login', ['email' => null, 'password' => 'senha-do-caixa-123'])
            ->assertStatus(422);
    }
}
