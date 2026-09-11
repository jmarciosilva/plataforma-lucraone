<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Address;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F3.6 — Company & Roles Management
 */
class CompanyAndRolesManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private User $admin;

    private Role $adminRole;

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

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    public function test_listagem_de_empresas_renderiza_apenas_tenant_atual(): void
    {
        Company::factory()->forCurrentTenant($this->tenantAtual->id)->create(['legal_name' => 'Casa Alta Comercio']);
        Company::factory()->forCurrentTenant($this->outroTenant->id)->create(['legal_name' => 'Outro Tenant Ltda']);

        $this->actingAs($this->admin)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('empresas')
            ->assertSee('ajuda de empresas')
            ->assertSee('empresa é o CNPJ', false)
            ->assertSee('nova empresa')
            ->assertSee('Casa Alta Comercio')
            ->assertDontSee('Outro Tenant Ltda');
    }

    public function test_criar_empresa_salva_no_tenant_atual(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('companies.store'), [
            'legal_name' => 'Nova Empresa Ltda',
            'trade_name' => 'Nova Empresa',
            'document' => '12.345.678/0001-90',
            'state_registration' => '110042490114',
            'municipal_registration' => '123456',
            'email' => 'financeiro@nova.test',
            'phone' => '(11) 99999-0000',
            'status' => 'ACTIVE',
        ]);

        $company = Company::where('document', '12.345.678/0001-90')->firstOrFail();

        $resposta->assertRedirect(route('companies.show', $company));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'tenant_id' => $this->tenantAtual->id,
            'legal_name' => 'Nova Empresa Ltda',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_documento_duplicado_no_mesmo_tenant_retorna_erro(): void
    {
        Company::factory()->forCurrentTenant($this->tenantAtual->id)->create(['document' => '00.000.000/0001-00']);

        $this->actingAs($this->admin)
            ->from(route('companies.create'))
            ->post(route('companies.store'), [
                'legal_name' => 'Documento Repetido',
                'document' => '00.000.000/0001-00',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('companies.create'))
            ->assertSessionHasErrors('document');
    }

    public function test_editar_empresa_atualiza_dados(): void
    {
        $company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->create(['legal_name' => 'Nome Antigo']);

        $this->actingAs($this->admin)
            ->put(route('companies.update', $company), [
                'legal_name' => 'Nome Novo Ltda',
                'trade_name' => 'Nome Novo',
                'document' => $company->document,
                'state_registration' => null,
                'municipal_registration' => null,
                'email' => 'contato@novo.test',
                'phone' => '11988887777',
                'status' => 'SUSPENDED',
            ])
            ->assertRedirect(route('companies.show', $company));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'legal_name' => 'Nome Novo Ltda',
            'status' => 'SUSPENDED',
            'email' => 'contato@novo.test',
        ]);
    }

    public function test_deletar_empresa_desativa_sem_apagar_registro(): void
    {
        $company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create();

        $this->actingAs($this->admin)
            ->delete(route('companies.destroy', $company))
            ->assertRedirect(route('companies.index', ['status' => 'INACTIVE']));

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'status' => 'INACTIVE',
        ]);
    }

    public function test_criar_e_editar_endereco_da_empresa(): void
    {
        $company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->create();

        $this->actingAs($this->admin)
            ->post(route('companies.addresses.store', $company), $this->payloadEndereco([
                'street' => 'Rua Primeira',
                'is_primary' => '1',
            ]))
            ->assertRedirect(route('companies.show', $company));

        $address = Address::where('addressable_id', $company->id)->firstOrFail();

        $this->assertTrue($address->is_primary);

        $this->actingAs($this->admin)
            ->put(route('companies.addresses.update', [$company, $address]), $this->payloadEndereco([
                'street' => 'Rua Atualizada',
                'number' => '456',
                'is_primary' => '1',
            ]))
            ->assertRedirect(route('companies.show', $company));

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'street' => 'Rua Atualizada',
            'number' => '456',
            'tenant_id' => $this->tenantAtual->id,
        ]);
    }

    public function test_listagem_de_roles_e_permissions_renderiza_corretamente(): void
    {
        $role = Role::factory()->forTenant($this->tenantAtual->id)->create([
            'id' => (string) Str::ulid(),
            'name' => 'operador',
        ]);
        $permission = Permission::factory()->forTenant($this->tenantAtual->id)->create([
            'name' => 'view-dashboard',
            'description' => 'ver dashboard',
        ]);
        $role->grantPermission($permission);

        $this->actingAs($this->admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('permissões')
            ->assertSee('ajuda de permissões')
            ->assertSee('operador')
            ->assertSee('catálogo');

        $this->actingAs($this->admin)
            ->get(route('permissions.index'))
            ->assertOk()
            ->assertSee('view-dashboard')
            ->assertSee('ver dashboard');
    }

    public function test_detalhe_de_role_mostra_permissoes_e_usuarios(): void
    {
        $role = Role::factory()->forTenant($this->tenantAtual->id)->create([
            'id' => (string) Str::ulid(),
            'name' => 'caixa',
        ]);
        $permission = Permission::factory()->forTenant($this->tenantAtual->id)->create(['name' => 'open-cashier']);
        $user = User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Bia Caixa']);

        $role->grantPermission($permission);
        $user->assignRole($role, $this->tenantAtual->id);

        $this->actingAs($this->admin)
            ->get(route('roles.show', $role))
            ->assertOk()
            ->assertSee('caixa')
            ->assertSee('open-cashier')
            ->assertSee('Bia Caixa')
            ->assertSee('atribuir permissões');
    }

    public function test_atribuir_permissions_a_role_salva_pivot_do_tenant(): void
    {
        $role = Role::factory()->forTenant($this->tenantAtual->id)->create(['id' => (string) Str::ulid()]);
        $permissionA = Permission::factory()->forTenant($this->tenantAtual->id)->create(['name' => 'permission-a']);
        $permissionB = Permission::factory()->forTenant($this->tenantAtual->id)->create(['name' => 'permission-b']);

        // SEC-04 · E1: só se delega permissão que se possui.
        $this->adminRole->grantPermission($permissionA);
        $this->adminRole->grantPermission($permissionB);

        $this->actingAs($this->admin)
            ->post(route('roles.permissions.sync', $role), [
                'permissions' => [$permissionA->id, $permissionB->id],
            ])
            ->assertRedirect(route('roles.show', $role));

        $this->assertTrue($role->fresh()->hasPermission($permissionA));
        $this->assertTrue($role->fresh()->hasPermission($permissionB));
        $this->assertDatabaseHas('role_permission', [
            'role_id' => $role->id,
            'permission_id' => $permissionA->id,
            'tenant_id' => $this->tenantAtual->id,
        ]);
    }

    public function test_usuario_sem_permissao_recebe_403_nos_modulos_da_sprint(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)
            ->get(route('companies.index'))
            ->assertForbidden();

        $this->actingAs($usuario)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        foreach (['create-role', 'update-role', 'view-roles', 'view-permissions', 'manage-companies', 'view-companies'] as $permissionName) {
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

    private function payloadEndereco(array $sobrescrever = []): array
    {
        return [
            'street' => 'Rua das Flores',
            'number' => '123',
            'complement' => null,
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postal_code' => '01310-100',
            'country' => 'BR',
            'is_primary' => '0',
            ...$sobrescrever,
        ];
    }
}
