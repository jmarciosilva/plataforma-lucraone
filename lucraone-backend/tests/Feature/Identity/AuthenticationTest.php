<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\TenancyTestCase;

class AuthenticationTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: Login com credenciais válidas retorna token.
     */
    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => 'test@example.com', 'password' => 'password']);

        $response = $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user', 'message']);
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Teste 02: Login com email inválido retorna 401.
     */
    public function test_login_with_invalid_email_returns_401(): void
    {
        $response = $this->post('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Credenciais inválidas']);
    }

    /**
     * Teste 03: Login com senha incorreta retorna 401.
     */
    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => 'test@example.com', 'password' => 'password']);

        $response = $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Credenciais inválidas']);
    }

    /**
     * Teste 04: Conta desativada globalmente não entra.
     */
    public function test_login_with_inactive_user_returns_403(): void
    {
        User::factory()
            ->inactive()
            ->forTenant($this->tenantA)
            ->create(['email' => 'inactive@example.com', 'password' => 'password']);

        $response = $this->post('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Conta inativa']);
    }

    /**
     * Teste 05: Vínculo apenas convidado ainda não dá acesso.
     *
     * A conta existe e a senha confere, mas o convite não foi aceito em
     * nenhum estabelecimento — não há onde entrar.
     */
    public function test_login_with_invited_user_returns_403(): void
    {
        User::factory()
            ->forTenant($this->tenantA, TenantUser::STATUS_INVITED)
            ->create(['email' => 'invited@example.com', 'password' => 'password']);

        $response = $this->post('/api/auth/login', [
            'email' => 'invited@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Usuário sem vínculo ativo com nenhum estabelecimento',
        ]);
    }

    /**
     * Teste 06: Login atualiza last_login_at.
     */
    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()
            ->active()
            ->neverLoggedIn()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => 'test@example.com', 'password' => 'password']);

        $this->assertNull($user->fresh()->last_login_at);

        $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /**
     * Teste 07: Login retorna dados do usuário corretos.
     */
    public function test_login_returns_correct_user_data(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => 'test@example.com', 'password' => 'password']);

        $response = $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => 'test@example.com',
                'status' => 'ACTIVE',
            ],
            // O estabelecimento não vem mais no usuário: vem na lista de
            // vínculos, de onde o cliente escolhe qual usar.
            'tenants' => [
                ['id' => $this->tenantA->id],
            ],
        ]);
    }

    /**
     * Teste 08: Logout com token válido revoga acesso.
     */
    public function test_logout_with_valid_token_revokes_access(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $response = $this->post('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $response->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Logout realizado com sucesso']);
    }

    /**
     * Teste 09: Logout sem token retorna 401.
     */
    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->post('/api/auth/logout');

        $response->assertStatus(401);
    }

    /**
     * Teste 10: Login válida credenciais não obtidas de outro tenant.
     */
    public function test_login_user_from_tenant_b_cannot_access_tenant_a_credentials(): void
    {
        // Uma pessoa, vínculo ativo no A e suspenso no B. O login devolve
        // apenas os estabelecimentos onde ela pode realmente operar.
        $usuario = User::factory()
            ->active()
            ->create(['email' => 'admin@example.com', 'password' => 'password']);

        $usuario->joinTenant($this->tenantA->id, TenantUser::STATUS_ACTIVE);
        $usuario->joinTenant($this->tenantB->id, TenantUser::STATUS_SUSPENDED);

        $response = $this->post('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'tenants');
        $response->assertJson([
            'tenants' => [
                ['id' => $this->tenantA->id],
            ],
        ]);
    }

    /**
     * Teste 11: Validação de email obrigatório.
     */
    public function test_login_requires_email(): void
    {
        $response = $this->post('/api/auth/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * Teste 12: Validação de senha obrigatória.
     */
    public function test_login_requires_password(): void
    {
        $response = $this->post('/api/auth/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }
}
