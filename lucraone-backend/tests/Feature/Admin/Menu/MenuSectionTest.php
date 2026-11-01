<?php

namespace Tests\Feature\Admin\Menu;

use App\Modules\Branches\Domain\BranchSegment;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Menu\Domain\Models\MenuSection;
use App\Modules\Tenancy\Application\BranchResolver;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rest02')]
#[Group('menu')]
class MenuSectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $empresa;
    private Branch $filial;
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

        $this->filial = Branch::factory()->forCompany($this->empresa)->active()->create([
            'segment' => BranchSegment::RESTAURANT,
        ]);

        $this->admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    private function comSessao()
    {
        return $this->actingAs($this->admin)
            ->withSession([
                'tenant_ativo' => $this->tenant->id,
                BranchResolver::SESSAO_FILIAL => $this->filial->id,
            ]);
    }

    public function test_admin_ve_cardapio_index(): void
    {
        MenuSection::factory()->forBranch($this->filial)->create(['name' => 'Entradas']);

        $this->comSessao()
            ->get(route('cardapio.index'))
            ->assertOk()
            ->assertSee('Entradas');
    }

    public function test_admin_cria_secao(): void
    {
        $this->comSessao()
            ->post(route('cardapio.store'), [
                'name' => 'Bebidas',
                'position' => 1,
                'available' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('menu_sections', [
            'name' => 'Bebidas',
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->filial->id,
        ]);
    }

    public function test_name_obrigatorio_na_criacao(): void
    {
        $this->comSessao()
            ->post(route('cardapio.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_edita_secao(): void
    {
        $section = MenuSection::factory()->forBranch($this->filial)->create(['name' => 'Antigas Entradas']);

        $this->comSessao()
            ->put(route('cardapio.update', $section), [
                'name' => 'Entradas Renovadas',
                'position' => 2,
                'available' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('menu_sections', [
            'id' => $section->id,
            'name' => 'Entradas Renovadas',
        ]);
    }

    public function test_toggle_disponibilidade(): void
    {
        $section = MenuSection::factory()->forBranch($this->filial)->create(['available' => true]);

        $this->comSessao()
            ->post(route('cardapio.toggle-available', $section))
            ->assertRedirect();

        $this->assertDatabaseHas('menu_sections', [
            'id' => $section->id,
            'available' => false,
        ]);
    }

    public function test_admin_remove_secao(): void
    {
        $section = MenuSection::factory()->forBranch($this->filial)->create();

        $this->comSessao()
            ->delete(route('cardapio.destroy', $section))
            ->assertRedirect(route('cardapio.index'));

        $this->assertDatabaseMissing('menu_sections', ['id' => $section->id]);
    }

    public function test_isolamento_por_tenant(): void
    {
        $outraTenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outraTenant);
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outraTenant->id, 'status' => 'ACTIVE']);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->active()->create(['segment' => BranchSegment::RESTAURANT]);
        MenuSection::factory()->forBranch($outraFilial)->create(['name' => 'Seção Outra Tenant']);

        $this->comSessao()
            ->get(route('cardapio.index'))
            ->assertOk()
            ->assertDontSee('Seção Outra Tenant');
    }
}
