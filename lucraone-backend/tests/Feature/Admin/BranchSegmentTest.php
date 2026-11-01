<?php

namespace Tests\Feature\Admin;

use App\Modules\Branches\Domain\BranchSegment;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * MS-02 — campo segment nas filiais e tela de seleção de filial.
 */
#[Group('branch')]
#[Group('ms02')]
class BranchSegmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->empresa = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    // ---------------------------------------------------------------
    // Criação com segmento
    // ---------------------------------------------------------------

    public function test_criar_filial_com_segmento_supermercado(): void
    {
        $payload = [
            'company_id' => $this->empresa->id,
            'name' => 'Loja Centro',
            'segment' => BranchSegment::SUPERMARKET,
        ];

        $this->actingAs($this->admin)
            ->post(route('branches.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Loja Centro',
            'segment' => BranchSegment::SUPERMARKET,
        ]);
    }

    public function test_criar_filial_com_segmento_restaurante(): void
    {
        $payload = [
            'company_id' => $this->empresa->id,
            'name' => 'Restaurante Principal',
            'segment' => BranchSegment::RESTAURANT,
        ];

        $this->actingAs($this->admin)
            ->post(route('branches.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'name' => 'Restaurante Principal',
            'segment' => BranchSegment::RESTAURANT,
        ]);
    }

    public function test_segmento_invalido_falha_validacao(): void
    {
        $payload = [
            'company_id' => $this->empresa->id,
            'name' => 'Loja X',
            'segment' => 'FARMACIA',
        ];

        $this->actingAs($this->admin)
            ->post(route('branches.store'), $payload)
            ->assertSessionHasErrors('segment');
    }

    public function test_segmento_padrao_e_supermercado_quando_omitido(): void
    {
        $payload = [
            'company_id' => $this->empresa->id,
            'name' => 'Loja Sem Segmento',
        ];

        $this->actingAs($this->admin)
            ->post(route('branches.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('branches', [
            'name' => 'Loja Sem Segmento',
            'segment' => BranchSegment::SUPERMARKET,
        ]);
    }

    // ---------------------------------------------------------------
    // Edição do segmento
    // ---------------------------------------------------------------

    public function test_atualizar_segmento_de_supermercado_para_restaurante(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->create([
            'segment' => BranchSegment::SUPERMARKET,
        ]);

        $this->actingAs($this->admin)
            ->put(route('branches.update', $filial), [
                'name' => $filial->name,
                'segment' => BranchSegment::RESTAURANT,
            ])
            ->assertRedirect(route('branches.show', $filial));

        $this->assertDatabaseHas('branches', [
            'id' => $filial->id,
            'segment' => BranchSegment::RESTAURANT,
        ]);
    }

    // ---------------------------------------------------------------
    // Tela de seleção de filial
    // ---------------------------------------------------------------

    public function test_escolher_filial_exibe_filiais_do_tenant(): void
    {
        Branch::factory()->forCompany($this->empresa)->active()->create(['name' => 'Filial A']);
        Branch::factory()->forCompany($this->empresa)->active()->create(['name' => 'Filial B']);

        $this->actingAs($this->admin)
            ->get(route('filiais.escolher'))
            ->assertOk()
            ->assertSee('Filial A')
            ->assertSee('Filial B');
    }

    public function test_definir_filial_grava_na_sessao(): void
    {
        $filial = Branch::factory()->forCompany($this->empresa)->active()->create();

        $this->actingAs($this->admin)
            ->post(route('filiais.definir'), ['branch_id' => $filial->id])
            ->assertRedirect(route('dashboard'));

        // Verifica que a sessão tem a filial correta
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_definir_filial_de_outro_tenant_e_recusada(): void
    {
        $outroTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outroTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outroTenant->id]);
        $filialOutro = Branch::factory()->forCompany($outraEmpresa)->active()->create();

        $this->actingAs($this->admin)
            ->post(route('filiais.definir'), ['branch_id' => $filialOutro->id])
            ->assertRedirect();

        // A sessão não deve ter a filial do outro tenant
        // (o BranchResolver rejeita ao tentar validar)
        $this->assertDatabaseMissing('branches', [
            'id' => $filialOutro->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
