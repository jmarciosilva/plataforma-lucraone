<?php

namespace Tests\Feature\Admin\Menu;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Menu\Application\MenuService;
use App\Modules\Menu\Domain\ModifierGroupType;
use App\Modules\Menu\Domain\Models\ModifierGroup;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rest02')]
#[Group('menu')]
class ModifierGroupTest extends TestCase
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

        $this->filial = Branch::factory()->forCompany($this->empresa)->active()->create();

        $this->admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin = $this->admin->fresh();
    }

    public function test_grupo_global_nao_tem_branch_id(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->global()->create();

        $this->assertTrue($group->isGlobal());
        $this->assertNull($group->branch_id);
    }

    public function test_grupo_override_tem_branch_id(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->forBranch($this->filial)->create();

        $this->assertFalse($group->isGlobal());
        $this->assertTrue($group->isBranchOverride());
        $this->assertEquals($this->filial->id, $group->branch_id);
    }

    public function test_resolucao_r2a_override_precede_global(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        ModifierGroup::factory()->forProduct($product)->global()->create(['name' => 'Grupo Global']);
        ModifierGroup::factory()->forProduct($product)->forBranch($this->filial)->create(['name' => 'Grupo Override']);

        $service = app(MenuService::class);
        $groups = $service->resolveModifierGroups($product->id, $this->filial->id, $this->tenant->id);

        $names = $groups->pluck('name')->all();
        $this->assertContains('Grupo Override', $names);
        $this->assertNotContains('Grupo Global', $names);
    }

    public function test_resolucao_r2a_sem_override_usa_global(): void
    {
        $outraFilial = Branch::factory()->forCompany($this->empresa)->active()->create();
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        ModifierGroup::factory()->forProduct($product)->global()->create(['name' => 'Global Visível']);
        ModifierGroup::factory()->forProduct($product)->forBranch($this->filial)->create(['name' => 'Override de Outra Filial']);

        $service = app(MenuService::class);
        $groups = $service->resolveModifierGroups($product->id, $outraFilial->id, $this->tenant->id);

        $names = $groups->pluck('name')->all();
        $this->assertContains('Global Visível', $names);
        $this->assertNotContains('Override de Outra Filial', $names);
    }

    public function test_tipo_valido_obrigatorio(): void
    {
        $this->assertFalse(ModifierGroupType::isValid('INVALIDO'));
        $this->assertTrue(ModifierGroupType::isValid(ModifierGroupType::ADDITIONAL));
        $this->assertTrue(ModifierGroupType::isValid(ModifierGroupType::REMOVAL));
        $this->assertTrue(ModifierGroupType::isValid(ModifierGroupType::CHOICE));
    }

    public function test_grupo_obrigatorio_exige_min_selections_positivo(): void
    {
        $group = ModifierGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'required' => true,
            'min_selections' => 1,
        ]);

        $this->assertTrue($group->required);
        $this->assertGreaterThan(0, $group->min_selections);
    }
}
