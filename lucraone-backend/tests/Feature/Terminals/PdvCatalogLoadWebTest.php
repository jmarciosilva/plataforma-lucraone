<?php

namespace Tests\Feature\Terminals;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\GeneratePdvCatalogPublication;
use App\Modules\Pdv\Domain\Models\PdvCatalogPublication;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantResolver;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdvCatalogLoadWebTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    private Branch $branch;

    private Terminal $terminal;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pdv_catalog.enabled' => true]);
        $this->tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->branch = Branch::factory()->forCompany($this->company)->active()->create();
        $this->terminal = Terminal::factory()->forBranch($this->branch)->create([
            'status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid(),
        ]);
        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $role = Role::factory()->forTenant($this->tenant->id)->create();
        foreach (['manage-products', 'view-companies', 'view-branches', 'manage-branches'] as $permissionName) {
            $permission = Permission::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'name' => $permissionName],
                ['id' => (string) Str::ulid(), 'description' => $permissionName],
            );
            $role->grantPermission($permission);
        }
        $this->admin->assignRole($role, $this->tenant->id);
    }

    public function test_tela_renderiza_status_de_conectividade_e_historico(): void
    {
        $this->terminal->update(['last_seen_at' => now()->subSeconds(45)]);
        $this->painel()->get(route('pdv.catalog-loads.index', ['company_id' => $this->company->id]))
            ->assertOk()->assertSee('central de cargas pdv')->assertSee('online')->assertSee('enviar carga de produtos e preços');

        $this->terminal->update(['last_seen_at' => now()->subSeconds(180)]);
        $this->painel()->get(route('pdv.catalog-loads.index', ['company_id' => $this->company->id]))
            ->assertOk()->assertSee('comunicação atrasada');

        $this->terminal->update(['last_seen_at' => now()->subHours(1)]);
        $this->painel()->get(route('pdv.catalog-loads.index', ['company_id' => $this->company->id]))
            ->assertOk()->assertSee('offline');
    }

    public function test_tela_fica_fechada_ate_ativacao_explicita_do_ambiente(): void
    {
        config(['pdv_catalog.enabled' => false]);

        $this->painel()->get(route('pdv.catalog-loads.index'))->assertNotFound();
    }

    public function test_terminal_pdvs_expoe_atalho_para_mesma_central_filtrada_com_autorizacao(): void
    {
        $this->painel()->get(route('terminals.show', $this->terminal))
            ->assertOk()
            ->assertSee('cargas deste terminal')
            ->assertSee('company_id='.$this->company->id)
            ->assertSee('terminal_id='.$this->terminal->id);

        $this->painel()->get(route('pdv.catalog-loads.index', [
            'company_id' => $this->company->id, 'terminal_id' => $this->terminal->id,
        ]))->assertOk()->assertSee($this->terminal->name);

        $foreignTenant = Tenant::factory()->active()->create();
        $foreignCompany = Company::factory()->forCurrentTenant($foreignTenant->id)->active()->create();
        $foreignBranch = Branch::factory()->forCompany($foreignCompany)->active()->create();
        $otherTerminal = Terminal::factory()->forBranch($foreignBranch)->create([
            'status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid(),
        ]);
        $this->painel()->get(route('pdv.catalog-loads.index', [
            'company_id' => $this->company->id, 'terminal_id' => $otherTerminal->id,
        ]))->assertNotFound();
    }

    public function test_publicacao_exige_agendamento_explicito_quando_terminal_esta_offline(): void
    {
        Queue::fake();
        $this->terminal->update(['last_seen_at' => now()->subMinutes(10)]);
        $payload = [
            'company_id' => $this->company->id,
            'terminal_ids' => [$this->terminal->id],
            'return_terminal_id' => $this->terminal->id,
        ];

        $this->painel()->post(route('pdv.catalog-loads.store'), $payload)->assertSessionHasErrors('schedule_offline');
        $this->assertDatabaseCount('pdv_catalog_publications', 0);

        $identity = $this->terminal->only(['id', 'tenant_id', 'company_id', 'branch_id', 'installation_id']);
        $this->painel()->post(route('pdv.catalog-loads.store'), $payload + ['schedule_offline' => '1'])
            ->assertRedirect(route('pdv.catalog-loads.index', [
                'company_id' => $this->company->id, 'terminal_id' => $this->terminal->id,
            ]));
        $this->assertDatabaseHas('pdv_catalog_publications', ['company_id' => $this->company->id, 'version' => 1, 'status' => PdvCatalogPublication::GENERATING]);
        $this->assertDatabaseHas('pdv_catalog_deliveries', ['terminal_id' => $this->terminal->id, 'status' => 'PENDING']);
        $this->assertSame($identity, $this->terminal->fresh()->only(array_keys($identity)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'publish', 'entity_type' => 'pdv_catalog_publication']);
        Queue::assertPushed(GeneratePdvCatalogPublication::class, 1);
    }

    public function test_usuario_sem_permissao_terminal_bloqueado_e_id_de_outro_tenant_sao_recusados(): void
    {
        $ordinary = User::factory()->forTenant($this->tenant)->create();
        $this->actingAs($ordinary)->withSession($this->tenantSession())->get(route('pdv.catalog-loads.index'))
            ->assertForbidden();

        $foreignTenant = Tenant::factory()->active()->create();
        $foreignCompany = Company::factory()->forCurrentTenant($foreignTenant->id)->active()->create();
        $foreignBranch = Branch::factory()->forCompany($foreignCompany)->active()->create();
        $foreignTerminal = Terminal::factory()->forBranch($foreignBranch)->create([
            'status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid(), 'last_seen_at' => now(),
        ]);
        $this->terminal->update(['status' => Terminal::STATUS_ACTIVE, 'last_seen_at' => now()]);
        $foreignResponse = $this->painel()->postJson(route('pdv.catalog-loads.store'), [
            'company_id' => $this->company->id, 'terminal_ids' => [$foreignTerminal->id],
        ]);
        $this->assertContains($foreignResponse->getStatusCode(), [403, 404], $foreignResponse->getContent());
        $this->assertDatabaseCount('pdv_catalog_publications', 0);

        $this->terminal->update(['status' => Terminal::STATUS_BLOCKED]);
        $this->painel()->post(route('pdv.catalog-loads.store'), [
            'company_id' => $this->company->id, 'terminal_ids' => [$this->terminal->id], 'schedule_offline' => '1',
        ])->assertSessionHasErrors('terminal_ids');
    }

    private function tenantSession(): array
    {
        return [TenantResolver::SESSAO_TENANT => $this->tenant->id, '_token' => 'pdv-catalog-load-csrf'];
    }

    private function painel(): static
    {
        return $this->actingAs($this->admin)->withSession($this->tenantSession());
    }
}
