<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Concerns\MontaCenariosDeAutorizacao;
use Tests\TestCase;

/**
 * SEC-04 · E2 — escalada por manage-users.
 *
 * Baseline de caracterização: os testes descrevem o comportamento SEGURO e
 * falham enquanto o vetor existir.
 *
 * Usa o papel manager real do seed, que recebe manage-users. A expectativa é
 * sobre o resultado — o papel admin não chega a ninguém —, e não sobre a forma
 * da recusa, que a correção ainda vai definir.
 */
#[Group('sec-04')]
#[Group('sec-04-e2')]
class UserRoleEscalationSecurityTest extends TestCase
{
    use MontaCenariosDeAutorizacao, RefreshDatabase;

    private Tenant $tenant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->provisionarPapeisPadrao();

        $this->manager = $this->membroComPapel($this->tenant, 'manager');
    }

    public function test_manage_users_nao_permite_autoatribuir_papel_admin(): void
    {
        $papelAdmin = $this->papel($this->tenant, 'admin');

        $this->actingAs($this->manager)
            ->put(route('users.update', $this->manager), [
                'name' => $this->manager->name,
                'email' => $this->manager->email,
                'account_status' => User::STATUS_ACTIVE,
                'status' => TenantUser::STATUS_ACTIVE,
                'roles' => [
                    $this->papel($this->tenant, 'manager')->id,
                    $papelAdmin->id,
                ],
            ]);

        $this->assertDatabaseMissing('user_role', [
            'user_id' => $this->manager->id,
            'role_id' => $papelAdmin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $manager = $this->manager->fresh();
        $this->assertFalse($manager->hasPermission('create-role', $this->tenant->id));
        $this->assertFalse($manager->hasPermission('update-role', $this->tenant->id));
    }

    /**
     * Uma segunda conta controlada pelo manager já com o papel admin é a mesma
     * escalada por outro caminho.
     */
    public function test_manage_users_nao_cria_conta_com_papel_admin(): void
    {
        $this->actingAs($this->manager)
            ->post(route('users.store'), [
                'name' => 'Conta Paralela',
                'email' => 'conta.paralela@example.test',
                'password' => 'senha-de-teste-123',
                'password_confirmation' => 'senha-de-teste-123',
                'status' => TenantUser::STATUS_ACTIVE,
                'roles' => [$this->papel($this->tenant, 'admin')->id],
            ]);

        $criada = User::where('email', 'conta.paralela@example.test')->first();

        $this->assertFalse(
            $criada !== null && $criada->hasRole('admin', $this->tenant->id),
            'a conta criada por quem só tem manage-users não pode nascer com o papel admin'
        );
    }

    /**
     * Bloquear só a autoelevação deixaria aberto elevar uma pessoa controlada
     * pelo atacante.
     */
    public function test_manage_users_nao_atribui_papel_admin_a_outra_pessoa(): void
    {
        $papelAdmin = $this->papel($this->tenant, 'admin');
        $comum = $this->membroComPapel($this->tenant, 'user');

        $this->actingAs($this->manager)
            ->put(route('users.update', $comum), [
                'name' => $comum->name,
                'email' => $comum->email,
                'account_status' => User::STATUS_ACTIVE,
                'status' => TenantUser::STATUS_ACTIVE,
                'roles' => [$papelAdmin->id],
            ]);

        $this->assertDatabaseMissing('user_role', [
            'user_id' => $comum->id,
            'role_id' => $papelAdmin->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertFalse($comum->fresh()->hasPermission('create-role', $this->tenant->id));
    }
}
