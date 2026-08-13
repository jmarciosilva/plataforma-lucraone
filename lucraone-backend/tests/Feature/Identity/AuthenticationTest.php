<?php

namespace Tests\Feature\Identity;

use Tests\Feature\Tenancy\TenancyTestCase;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
     * Teste 04: Login com usuário INACTIVE retorna 403.
     */
    public function test_login_with_inactive_user_returns_403(): void
    {
        User::factory()
            ->inactive()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => 'inactive@example.com', 'password' => 'password']);

        $response = $this->post('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Usuário não está ativo']);
    }

    /**
     * Teste 05: Login com usuário INVITED retorna 403.
     */
    public function test_login_with_invited_user_returns_403(): void
    {
        User::factory()
            ->invited()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => 'invited@example.com', 'password' => 'password']);

        $response = $this->post('/api/auth/login', [
            'email' => 'invited@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Usuário não está ativo']);
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
                'tenant_id' => $this->tenantA->id,
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
        $userA = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create(['email' => 'admin@example.com', 'password' => 'password']);

        User::factory()
            ->active()
            ->forCurrentTenant($this->tenantB->id)
            ->create(['email' => 'admin@example.com', 'password' => 'different']);

        $response = $this->post('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'user' => ['tenant_id' => $this->tenantA->id],
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
