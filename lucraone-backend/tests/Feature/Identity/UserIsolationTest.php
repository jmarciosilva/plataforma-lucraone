<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\TenancyTestCase;

/**
 * Isolamento de usuários sob o modelo de identidade (F1.8).
 *
 * A identidade é global — uma pessoa, uma conta, uma senha. O isolamento não
 * vem mais de um tenant_id na linha do usuário, e sim do vínculo em
 * tenant_user: quem não tem vínculo ativo com o estabelecimento não opera nele.
 */
class UserIsolationTest extends TenancyTestCase
{
    use RefreshDatabase;

    public function test_vinculos_de_estabelecimentos_diferentes_coexistem(): void
    {
        $userA = User::factory()->forTenant($this->tenantA)->create();
        $userB = User::factory()->forTenant($this->tenantB)->create();

        $this->assertDatabaseHas('tenant_user', [
            'user_id' => $userA->id,
            'tenant_id' => $this->tenantA->id,
            'status' => TenantUser::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'user_id' => $userB->id,
            'tenant_id' => $this->tenantB->id,
            'status' => TenantUser::STATUS_ACTIVE,
        ]);
    }

    public function test_estabelecimento_lista_apenas_seus_vinculados(): void
    {
        $userA1 = User::factory()->forTenant($this->tenantA)->create();
        $userA2 = User::factory()->forTenant($this->tenantA)->create();
        User::factory()->forTenant($this->tenantB)->create();

        $usuarios = $this->tenantA->users()->get();

        $this->assertCount(2, $usuarios);
        $this->assertTrue($usuarios->contains('id', $userA1->id));
        $this->assertTrue($usuarios->contains('id', $userA2->id));
    }

    public function test_pessoa_sem_vinculo_nao_acessa_o_estabelecimento(): void
    {
        $userB = User::factory()->forTenant($this->tenantB)->create();

        $this->assertFalse($userB->canAccessTenant($this->tenantA->id));
        $this->assertTrue($userB->canAccessTenant($this->tenantB->id));
    }

    public function test_mesma_pessoa_atende_dois_estabelecimentos(): void
    {
        $contador = User::factory()->create();
        $contador->joinTenant($this->tenantA->id);
        $contador->joinTenant($this->tenantB->id);

        $this->assertTrue($contador->canAccessTenant($this->tenantA->id));
        $this->assertTrue($contador->canAccessTenant($this->tenantB->id));
        $this->assertCount(2, $contador->estabelecimentosDisponiveis());

        // Uma conta só, uma senha só
        $this->assertDatabaseCount('users', 1);
    }

    public function test_desativar_vinculo_nao_afeta_o_outro_estabelecimento(): void
    {
        $contador = User::factory()->create();
        $contador->joinTenant($this->tenantA->id);
        $contador->joinTenant($this->tenantB->id);

        // A Casa A revoga o acesso
        $contador->joinTenant($this->tenantA->id, TenantUser::STATUS_INACTIVE);

        $this->assertFalse($contador->canAccessTenant($this->tenantA->id));
        $this->assertTrue($contador->canAccessTenant($this->tenantB->id));

        // A conta em si continua ativa
        $this->assertTrue($contador->fresh()->isActive());
    }

    public function test_conta_inativa_perde_acesso_a_todos_os_estabelecimentos(): void
    {
        $usuario = User::factory()->create();
        $usuario->joinTenant($this->tenantA->id);
        $usuario->joinTenant($this->tenantB->id);

        $usuario->update(['status' => User::STATUS_INACTIVE]);

        $this->assertFalse($usuario->canAccessTenant($this->tenantA->id));
        $this->assertFalse($usuario->canAccessTenant($this->tenantB->id));
    }

    public function test_vinculo_convidado_ainda_nao_da_acesso(): void
    {
        $convidado = User::factory()->create();
        $convidado->joinTenant($this->tenantA->id, TenantUser::STATUS_INVITED);

        $this->assertFalse($convidado->canAccessTenant($this->tenantA->id));
        $this->assertCount(0, $convidado->estabelecimentosDisponiveis());
    }

    public function test_vinculo_suspenso_bloqueia_o_acesso(): void
    {
        $usuario = User::factory()->create();
        $usuario->joinTenant($this->tenantA->id, TenantUser::STATUS_SUSPENDED);

        $this->assertFalse($usuario->canAccessTenant($this->tenantA->id));
    }

    public function test_email_e_unico_globalmente(): void
    {
        User::factory()->create(['email' => 'pessoa@example.com']);

        // O mesmo e-mail em outro estabelecimento seria outra conta para a
        // mesma pessoa — exatamente o que o modelo de identidade evita.
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['email' => 'pessoa@example.com']);
    }

    public function test_uma_pessoa_tem_no_maximo_um_vinculo_por_estabelecimento(): void
    {
        $usuario = User::factory()->create();

        $usuario->joinTenant($this->tenantA->id);
        $usuario->joinTenant($this->tenantA->id, TenantUser::STATUS_SUSPENDED);

        $this->assertDatabaseCount('tenant_user', 1);
        $this->assertSame(
            TenantUser::STATUS_SUSPENDED,
            $usuario->membershipFor($this->tenantA->id)->status
        );
    }

    public function test_estados_de_conta_funcionam(): void
    {
        $ativo = User::factory()->active()->create();
        $inativo = User::factory()->inactive()->create();

        $this->assertTrue($ativo->isActive());
        $this->assertFalse($inativo->isActive());
    }

    public function test_usuario_ativo_tem_email_verificado(): void
    {
        $this->assertNotNull(User::factory()->active()->create()->email_verified_at);
    }

    public function test_convidado_nao_tem_email_verificado(): void
    {
        $this->assertNull(User::factory()->invited()->create()->email_verified_at);
    }

    public function test_senha_e_sempre_hasheada(): void
    {
        $usuario = User::factory()->create();

        $this->assertNotEquals('password', $usuario->password);
        $this->assertTrue(password_verify('password', $usuario->password));
    }
}
