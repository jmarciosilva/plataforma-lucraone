<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F3.4 — Tenant Management
 */
class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->admin = User::factory()->forTenant($this->tenantAtual)->create([
            'name' => 'Ana Admin',
        ]);

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    public function test_listagem_de_tenants_renderiza_corretamente(): void
    {
        Tenant::factory()->active()->create(['name' => 'Padaria Central', 'slug' => 'padaria-central']);

        $this->actingAs($this->admin)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('tenants')
            ->assertSee('ajuda de tenants')
            ->assertSee('tenant é o espaço isolado', false)
            ->assertSee('novo tenant')
            ->assertSee('Casa Alta')
            ->assertSee('Padaria Central');
    }

    public function test_busca_filtra_tenants_por_nome_ou_slug(): void
    {
        Tenant::factory()->active()->create(['name' => 'Mercado Norte', 'slug' => 'mercado-norte']);
        Tenant::factory()->active()->create(['name' => 'Padaria Central', 'slug' => 'padaria-central']);

        $this->actingAs($this->admin)
            ->get(route('tenants.index', ['search' => 'norte']))
            ->assertOk()
            ->assertSee('Mercado Norte')
            ->assertDontSee('Padaria Central');
    }

    public function test_filtro_por_status_funciona(): void
    {
        Tenant::factory()->active()->create(['name' => 'Ativo Demo']);
        Tenant::factory()->suspended()->create(['name' => 'Suspenso Demo']);

        $this->actingAs($this->admin)
            ->get(route('tenants.index', ['status' => 'SUSPENDED']))
            ->assertOk()
            ->assertSee('Suspenso Demo')
            ->assertDontSee('Ativo Demo');
    }

    public function test_ordenacao_por_nome_funciona(): void
    {
        Tenant::factory()->active()->create(['name' => 'Zulu Loja']);
        Tenant::factory()->active()->create(['name' => 'Alpha Loja']);

        $this->actingAs($this->admin)
            ->get(route('tenants.index', ['sort' => 'name']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Loja', 'Casa Alta', 'Zulu Loja']);
    }

    public function test_criar_tenant_salva_no_banco_com_slug_automatico(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('tenants.store'), [
            'name' => 'Loja Nova',
            'status' => 'ACTIVE',
            'plan' => 'standard',
            'timezone' => 'America/Sao_Paulo',
            'locale' => 'pt-BR',
            'currency' => 'BRL',
        ]);

        $tenant = Tenant::where('slug', 'loja-nova')->firstOrFail();

        $resposta->assertRedirect(route('tenants.show', $tenant));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Loja Nova',
            'slug' => 'loja-nova',
            'plan' => 'standard',
            'active' => true,
        ]);

        $this->assertTrue($this->admin->canAccessTenant($tenant->id));
        $this->assertTrue($this->admin->hasPermission('create-role', $tenant->id));
    }

    public function test_validacao_server_side_funciona(): void
    {
        $this->actingAs($this->admin)
            ->from(route('tenants.create'))
            ->post(route('tenants.store'), [
                'name' => '',
                'slug' => 'slug invalido',
                'status' => 'ARCHIVED',
                'plan' => 'premium',
                'timezone' => 'Mars/Phobos',
                'locale' => 'es-ES',
                'currency' => 'EUR',
            ])
            ->assertRedirect(route('tenants.create'))
            ->assertSessionHasErrors(['name', 'slug', 'status', 'plan', 'timezone', 'locale', 'currency']);
    }

    public function test_editar_tenant_atualiza_dados(): void
    {
        $tenant = Tenant::factory()->trial()->create(['name' => 'Antigo Nome']);

        $this->actingAs($this->admin)
            ->put(route('tenants.update', $tenant), [
                'name' => 'Novo Nome',
                'slug' => 'novo-nome',
                'status' => 'SUSPENDED',
                'plan' => 'enterprise',
                'timezone' => 'UTC',
                'locale' => 'en-US',
                'currency' => 'USD',
            ])
            ->assertRedirect(route('tenants.show', $tenant));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Novo Nome',
            'slug' => 'novo-nome',
            'status' => 'SUSPENDED',
            'plan' => 'enterprise',
            'active' => false,
        ]);
    }

    public function test_detalhe_exibe_informacoes_do_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create(['name' => 'Detalhe Loja', 'slug' => 'detalhe-loja']);

        $this->actingAs($this->admin)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSee('Detalhe Loja')
            ->assertSee('detalhe-loja')
            ->assertSee('timezone')
            ->assertSee('locale e moeda');
    }

    public function test_deletar_tenant_faz_soft_delete(): void
    {
        $tenant = Tenant::factory()->active()->create(['name' => 'Arquivo Loja']);

        $this->actingAs($this->admin)
            ->delete(route('tenants.destroy', $tenant))
            ->assertRedirect(route('tenants.index', ['trashed' => 'with']));

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_restaurar_tenant_recupera_soft_delete(): void
    {
        $tenant = Tenant::factory()->active()->create(['name' => 'Volta Loja']);
        $tenant->delete();

        $this->actingAs($this->admin)
            ->post(route('tenants.restore', $tenant->id))
            ->assertRedirect(route('tenants.show', $tenant));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'deleted_at' => null,
        ]);
    }

    public function test_paginacao_limita_listagem_em_vinte_por_pagina(): void
    {
        Tenant::factory(25)->active()->create();

        $this->actingAs($this->admin)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('Next', false);
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)
            ->get(route('tenants.index'))
            ->assertForbidden();
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        $permission = Permission::factory()
            ->forTenant($tenant->id)
            ->create([
                'name' => 'create-role',
                'description' => 'administra tenant management',
            ]);

        $role = Role::factory()
            ->admin()
            ->forTenant($tenant->id)
            ->create(['id' => (string) Str::ulid()]);

        $role->grantPermission($permission);
        $user->assignRole($role, $tenant->id);
    }
}
