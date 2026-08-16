<?php

namespace Tests\Feature\Identity;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenancy\TenancyTestCase;

/**
 * O caso que motivou o modelo de identidade (F1.8): uma pessoa que atende
 * mais de um estabelecimento — um dono com duas lojas, um contador com
 * vários clientes.
 *
 * Uma conta, uma senha, papéis independentes em cada estabelecimento.
 */
class MultiTenantIdentityTest extends TenancyTestCase
{
    use RefreshDatabase;

    private User $contador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contador = User::factory()->create([
            'email' => 'contador@escritorio.com',
            'password' => 'senha-unica',
        ]);

        $this->contador->joinTenant($this->tenantA->id);
        $this->contador->joinTenant($this->tenantB->id);
    }

    public function test_uma_conta_atende_dois_estabelecimentos(): void
    {
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenant_user', 2);

        $this->assertCount(2, $this->contador->estabelecimentosDisponiveis());
    }

    public function test_a_mesma_senha_vale_para_os_dois(): void
    {
        // Uma senha só — não há uma credencial por estabelecimento
        $this->assertTrue(password_verify('senha-unica', $this->contador->password));

        foreach ([$this->tenantA, $this->tenantB] as $estabelecimento) {
            $this->assertTrue($this->contador->canAccessTenant($estabelecimento->id));
        }
    }

    public function test_papeis_sao_independentes_por_estabelecimento(): void
    {
        $admin = Role::factory()->forTenant($this->tenantA->id)->create(['name' => 'admin']);
        $viewer = Role::factory()->forTenant($this->tenantB->id)->create(['name' => 'viewer']);

        $this->contador->assignRole($admin, $this->tenantA->id);
        $this->contador->assignRole($viewer, $this->tenantB->id);

        // Admin na Casa A, mas não na Casa B
        $this->assertTrue($this->contador->hasRole('admin', $this->tenantA->id));
        $this->assertFalse($this->contador->hasRole('admin', $this->tenantB->id));

        // Viewer na Casa B, mas não na Casa A
        $this->assertTrue($this->contador->hasRole('viewer', $this->tenantB->id));
        $this->assertFalse($this->contador->hasRole('viewer', $this->tenantA->id));
    }

    public function test_permissoes_seguem_o_estabelecimento_ativo(): void
    {
        $papelA = Role::factory()->forTenant($this->tenantA->id)->create();
        $permissaoA = Permission::factory()->forTenant($this->tenantA->id)->create();
        $papelA->grantPermission($permissaoA);
        $this->contador->assignRole($papelA, $this->tenantA->id);

        // Com a Casa A ativa, a permissão vale
        $this->tenantContext->set($this->tenantA->id);
        $this->assertTrue($this->contador->hasPermission($permissaoA->name));

        // Trocando para a Casa B, a mesma permissão não vale
        $this->tenantContext->set($this->tenantB->id);
        $this->assertFalse($this->contador->hasPermission($permissaoA->name));
    }

    public function test_sincronizar_papeis_de_um_nao_mexe_no_outro(): void
    {
        $adminA = Role::factory()->forTenant($this->tenantA->id)->create(['name' => 'admin']);
        $viewerB = Role::factory()->forTenant($this->tenantB->id)->create(['name' => 'viewer']);

        $this->contador->assignRole($adminA, $this->tenantA->id);
        $this->contador->assignRole($viewerB, $this->tenantB->id);

        // Zera os papéis na Casa A
        $this->contador->syncRoles([], $this->tenantA->id);

        $this->assertFalse($this->contador->hasRole('admin', $this->tenantA->id));
        $this->assertTrue(
            $this->contador->hasRole('viewer', $this->tenantB->id),
            'papéis do outro estabelecimento devem permanecer intactos'
        );
    }

    public function test_login_lista_os_estabelecimentos_disponiveis(): void
    {
        $resposta = $this->postJson('/api/auth/login', [
            'email' => 'contador@escritorio.com',
            'password' => 'senha-unica',
        ]);

        $resposta->assertStatus(200)
            ->assertJsonCount(2, 'tenants')
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
                'tenants' => [['id', 'name', 'slug']],
            ]);
    }

    public function test_revogar_um_vinculo_reduz_a_lista_de_login(): void
    {
        $this->contador->joinTenant($this->tenantA->id, TenantUser::STATUS_INACTIVE);

        $resposta = $this->postJson('/api/auth/login', [
            'email' => 'contador@escritorio.com',
            'password' => 'senha-unica',
        ]);

        $resposta->assertStatus(200)
            ->assertJsonCount(1, 'tenants')
            ->assertJson(['tenants' => [['id' => $this->tenantB->id]]]);
    }

    public function test_header_de_estabelecimento_sem_vinculo_e_recusado(): void
    {
        $forasteiro = User::factory()->forTenant($this->tenantA)->create();
        $token = $forasteiro->createToken('teste')->plainTextToken;

        // Tenta operar num estabelecimento onde não tem vínculo
        $resposta = $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenantB->id)
            ->getJson('/api/v1/products');

        $this->assertNotEquals(200, $resposta->status());
    }

    public function test_vinculo_unico_dispensa_escolha(): void
    {
        $funcionario = User::factory()->forTenant($this->tenantA)->create();
        $token = $funcionario->createToken('teste')->plainTextToken;

        // Sem X-Tenant-ID: só há um estabelecimento possível
        $resposta = $this->withToken($token)->getJson('/api/v1/products');

        $resposta->assertStatus(200);
    }
}
