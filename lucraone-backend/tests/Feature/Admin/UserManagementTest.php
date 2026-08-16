<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F3.5 — User Management
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private User $admin;

    private Role $adminRole;

    private Role $operatorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);

        $this->admin = User::factory()->forTenant($this->tenantAtual)->create([
            'name' => 'Ana Admin',
        ]);

        $this->adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantAtual->id)
            ->create(['id' => (string) Str::ulid()]);

        $this->operatorRole = Role::factory()
            ->forTenant($this->tenantAtual->id)
            ->create([
                'id' => (string) Str::ulid(),
                'name' => 'operador',
                'description' => 'Operador do painel',
            ]);

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    public function test_listagem_de_usuarios_renderiza_apenas_tenant_atual(): void
    {
        User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Bruno Caixa']);
        User::factory()->forTenant($this->outroTenant)->create(['name' => 'Clara Outro Tenant']);

        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('usuários')
            ->assertSee('novo usuário')
            ->assertSee('Bruno Caixa')
            ->assertDontSee('Clara Outro Tenant');
    }

    public function test_busca_filtra_por_nome_ou_email(): void
    {
        User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Bruno Caixa', 'email' => 'bruno@example.com']);
        User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Carla Balcao', 'email' => 'carla@example.com']);

        $this->actingAs($this->admin)
            ->get(route('users.index', ['search' => 'bruno']))
            ->assertOk()
            ->assertSee('Bruno Caixa')
            ->assertDontSee('Carla Balcao');
    }

    public function test_filtro_por_status_do_vinculo_funciona(): void
    {
        User::factory()->forTenant($this->tenantAtual, TenantUser::STATUS_ACTIVE)->create(['name' => 'Ativo User']);
        User::factory()->forTenant($this->tenantAtual, TenantUser::STATUS_SUSPENDED)->create(['name' => 'Suspenso User']);

        $this->actingAs($this->admin)
            ->get(route('users.index', ['status' => TenantUser::STATUS_SUSPENDED]))
            ->assertOk()
            ->assertSee('Suspenso User')
            ->assertDontSee('Ativo User');
    }

    public function test_criar_usuario_salva_identidade_vinculo_e_role(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Operador Novo',
            'email' => 'operador.novo@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'status' => TenantUser::STATUS_ACTIVE,
            'roles' => [$this->operatorRole->id],
        ]);

        $user = User::where('email', 'operador.novo@example.com')->firstOrFail();

        $resposta->assertRedirect(route('users.show', $user));

        $this->assertTrue(Hash::check('12345678', $user->password));
        $this->assertTrue($user->canAccessTenant($this->tenantAtual->id));
        $this->assertTrue($user->hasRole('operador', $this->tenantAtual->id));
    }

    public function test_email_duplicado_no_mesmo_tenant_retorna_erro(): void
    {
        User::factory()->forTenant($this->tenantAtual)->create(['email' => 'duplicado@example.com']);

        $this->actingAs($this->admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Duplicado',
                'email' => 'duplicado@example.com',
                'password' => '12345678',
                'password_confirmation' => '12345678',
                'status' => TenantUser::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('email');
    }

    public function test_email_existente_em_outro_tenant_pode_ser_vinculado(): void
    {
        $existente = User::factory()->forTenant($this->outroTenant)->create([
            'email' => 'multi@example.com',
        ]);

        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Pessoa Multi',
            'email' => 'multi@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'status' => TenantUser::STATUS_ACTIVE,
            'roles' => [$this->operatorRole->id],
        ])->assertRedirect(route('users.show', $existente));

        $this->assertTrue($existente->fresh()->canAccessTenant($this->tenantAtual->id));
        $this->assertTrue($existente->fresh()->hasRole('operador', $this->tenantAtual->id));
    }

    public function test_editar_usuario_atualiza_dados_status_senha_e_roles(): void
    {
        $user = User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Nome Antigo']);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), [
                'name' => 'Nome Novo',
                'email' => $user->email,
                'account_status' => User::STATUS_ACTIVE,
                'status' => TenantUser::STATUS_SUSPENDED,
                'password' => 'nova-senha-123',
                'password_confirmation' => 'nova-senha-123',
                'roles' => [$this->operatorRole->id],
            ])
            ->assertRedirect(route('users.show', $user));

        $user->refresh();

        $this->assertSame('Nome Novo', $user->name);
        $this->assertFalse($user->canAccessTenant($this->tenantAtual->id));
        $this->assertTrue(Hash::check('nova-senha-123', $user->password));
        $this->assertTrue($user->hasRole('operador', $this->tenantAtual->id));
    }

    public function test_resetar_senha_altera_password(): void
    {
        $user = User::factory()->forTenant($this->tenantAtual)->create();
        $senhaAnterior = $user->password;

        $this->actingAs($this->admin)
            ->post(route('users.reset-password', $user))
            ->assertRedirect(route('users.show', $user))
            ->assertSessionHas('senha_temporaria');

        $this->assertNotSame($senhaAnterior, $user->fresh()->password);
    }

    public function test_deletar_usuario_de_um_tenant_faz_soft_delete(): void
    {
        $user = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index', ['trashed' => 'with']));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_deletar_usuario_multi_tenant_preserva_identidade_e_desativa_vinculo_atual(): void
    {
        $user = User::factory()->forTenant($this->tenantAtual)->create();
        $user->joinTenant($this->outroTenant->id, TenantUser::STATUS_ACTIVE);

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index', ['trashed' => 'with']));

        $this->assertNull($user->fresh()->deleted_at);
        $this->assertSame(TenantUser::STATUS_INACTIVE, $user->membershipFor($this->tenantAtual->id)->status);
        $this->assertTrue($user->canAccessTenant($this->outroTenant->id));
    }

    public function test_restaurar_usuario_recupera_acesso(): void
    {
        $user = User::factory()->forTenant($this->tenantAtual)->create();
        $user->delete();

        $this->actingAs($this->admin)
            ->post(route('users.restore', $user->id))
            ->assertRedirect(route('users.show', $user));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->assertTrue($user->fresh()->canAccessTenant($this->tenantAtual->id));
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        foreach (['manage-users', 'view-users'] as $permissionName) {
            $permission = Permission::factory()
                ->forTenant($tenant->id)
                ->create([
                    'name' => $permissionName,
                    'description' => $permissionName,
                ]);

            $this->adminRole->grantPermission($permission);
        }

        $user->assignRole($this->adminRole, $tenant->id);
    }
}
