<?php

namespace Tests\Feature\Admin;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Tela de provisionamento DIRETO de terminal PDV no painel administrativo.
 *
 * Caminho de SESSÃO humana (não de API): a controller web chama o
 * TerminalProvisioningService sob a mesma guarda (policy create Terminal) e
 * contexto de estabelecimento das outras telas admin. Cobre a renderização do
 * formulário, o controle de acesso, o isolamento de tenant, a exibição única do
 * token e as recusas de validação.
 */
#[Group('terminal')]
#[Group('terminal-admin')]
#[Group('terminal-provisioning')]
class TerminalProvisioningWebTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $empresa;

    private Branch $filial;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Rede Mercado Ltda']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->empresa = Company::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'ACTIVE']);
        $this->filial = Branch::factory()->forCompany($this->empresa)->create([
            'name' => 'Loja Principal',
            'max_pdv_terminals' => 2,
        ]);

        $this->admin = User::factory()->create(['name' => 'Ana Admin']);
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    public function test_admin_acessa_formulario_de_provisionamento(): void
    {
        $this->actingAs($this->admin)
            ->get(route('terminals.provision'))
            ->assertOk()
            ->assertViewIs('terminals.provision')
            ->assertViewHas('filiais')
            ->assertSee('id de instalação');
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $visitante = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($visitante)
            ->get(route('terminals.provision'))
            ->assertForbidden();

        $this->actingAs($visitante)
            ->post(route('terminals.provision.store'), [
                'installation_id' => (string) Str::uuid(),
                'name' => 'Caixa X',
                'branch_id' => $this->filial->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('terminals', ['name' => 'Caixa X']);
    }

    public function test_admin_de_outro_tenant_nao_ve_filiais_alheias(): void
    {
        $outraEmpresa = Company::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'ACTIVE']);
        Branch::factory()->forCompany($outraEmpresa)->create(['name' => 'Loja Visível']);

        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $empresaAlheia = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        Branch::factory()->forCompany($empresaAlheia)->create(['name' => 'Filial Secreta']);

        $this->actingAs($this->admin)
            ->get(route('terminals.provision'))
            ->assertOk()
            ->assertSee('Loja Visível')
            ->assertDontSee('Filial Secreta');
    }

    public function test_admin_provisiona_terminal_ativo_e_recebe_token_uma_vez(): void
    {
        $uuid = (string) Str::uuid();

        $resposta = $this->actingAs($this->admin)
            ->post(route('terminals.provision.store'), [
                'installation_id' => $uuid,
                'name' => 'Caixa 001',
                'branch_id' => $this->filial->id,
            ]);

        $terminal = Terminal::withoutGlobalScopes()->where('installation_id', $uuid)->sole();

        $resposta->assertRedirect(route('terminals.show', $terminal->id));
        $resposta->assertSessionHas('credencialProvisionada');

        $this->assertSame(Terminal::STATUS_ACTIVE, $terminal->status);
        $this->assertSame($this->filial->id, $terminal->branch_id);
        $this->assertSame('Caixa 001', $terminal->name);
        $this->assertSame(1, $this->credenciaisDeMaquina());

        // A tela de detalhe (próximo request, que recebe o flash) exibe o token
        // em destaque, com o aviso de uso único.
        $token = $resposta->getSession()->get('credencialProvisionada');
        $this->assertNotEmpty($token);

        $this->actingAs($this->admin)
            ->get(route('terminals.show', $terminal->id))
            ->assertOk()
            ->assertSee('Guarde este token agora. Ele não será exibido novamente.')
            ->assertSee($token);

        // Consumido o flash, uma nova visita à tela não mostra mais o token.
        $this->actingAs($this->admin)
            ->get(route('terminals.show', $terminal->id))
            ->assertOk()
            ->assertDontSee($token)
            ->assertDontSee('Guarde este token agora. Ele não será exibido novamente.');
    }

    public function test_installation_id_duplicado_e_recusado(): void
    {
        $uuid = (string) Str::uuid();
        Terminal::factory()->forBranch($this->filial)->create([
            'installation_id' => $uuid,
            'status' => Terminal::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->post(route('terminals.provision.store'), [
                'installation_id' => $uuid,
                'name' => 'Caixa Dup',
                'branch_id' => $this->filial->id,
            ])
            ->assertSessionHasErrors(['installation_id']);

        $this->assertDatabaseMissing('terminals', ['name' => 'Caixa Dup']);
    }

    public function test_installation_id_malformado_e_recusado(): void
    {
        $this->actingAs($this->admin)
            ->post(route('terminals.provision.store'), [
                'installation_id' => 'nao-e-uuid',
                'name' => 'Caixa 002',
                'branch_id' => $this->filial->id,
            ])
            ->assertSessionHasErrors(['installation_id']);

        $this->assertDatabaseMissing('terminals', ['name' => 'Caixa 002']);
    }

    public function test_branch_de_outro_tenant_e_recusada(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $empresaAlheia = Company::factory()->create(['tenant_id' => $outraTenant->id]);
        $filialAlheia = Branch::factory()->forCompany($empresaAlheia)->create();

        $this->actingAs($this->admin)
            ->post(route('terminals.provision.store'), [
                'installation_id' => (string) Str::uuid(),
                'name' => 'Caixa Intruso',
                'branch_id' => $filialAlheia->id,
            ])
            ->assertSessionHasErrors(['branch_id']);

        $this->assertDatabaseMissing('terminals', ['name' => 'Caixa Intruso']);
    }

    public function test_cota_esgotada_e_recusada(): void
    {
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid()]);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid()]);

        $this->actingAs($this->admin)
            ->post(route('terminals.provision.store'), [
                'installation_id' => (string) Str::uuid(),
                'name' => 'Caixa Excedente',
                'branch_id' => $this->filial->id,
            ])
            ->assertSessionHasErrors(['branch_id']);

        $this->assertDatabaseMissing('terminals', ['name' => 'Caixa Excedente']);
    }

    private function credenciaisDeMaquina(): int
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new Terminal)->getMorphClass())
            ->count();
    }
}
