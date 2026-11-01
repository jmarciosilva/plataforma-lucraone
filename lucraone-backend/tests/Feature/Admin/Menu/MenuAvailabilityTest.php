<?php

namespace Tests\Feature\Admin\Menu;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Menu\Application\MenuService;
use App\Modules\Menu\Domain\ModifierGroupType;
use App\Modules\Menu\Domain\Models\Modifier;
use App\Modules\Menu\Domain\Models\ModifierGroup;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rest02')]
#[Group('menu')]
class MenuAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $filial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $empresa = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->filial = Branch::factory()->forCompany($empresa)->active()->create();
    }

    public function test_disponibilidade_persiste_apos_reinicializacao(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->global()->create(['name' => 'Adicionais']);
        $modifier = Modifier::factory()->forGroup($group)->create(['available' => true]);

        $modifier->update(['available' => false]);

        $modifier->refresh();
        $this->assertFalse($modifier->available);
    }

    public function test_alerta_grupo_choice_obrigatorio_sem_opcoes_disponiveis(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->global()->create([
            'type' => ModifierGroupType::CHOICE,
            'required' => true,
            'min_selections' => 1,
        ]);
        Modifier::factory()->forGroup($group)->unavailable()->create();

        $service = app(MenuService::class);
        $groups = $service->resolveModifierGroups($product->id, $this->filial->id, $this->tenant->id);
        $alerts = $service->choiceGroupsWithoutAvailableModifiers($groups);

        $this->assertCount(1, $alerts);
        $this->assertEquals($group->id, $alerts->first()->id);
    }

    public function test_sem_alerta_quando_grupo_choice_tem_opcao_disponivel(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->global()->create([
            'type' => ModifierGroupType::CHOICE,
            'required' => true,
            'min_selections' => 1,
        ]);
        Modifier::factory()->forGroup($group)->create(['available' => true]);

        $service = app(MenuService::class);
        $groups = $service->resolveModifierGroups($product->id, $this->filial->id, $this->tenant->id);
        $alerts = $service->choiceGroupsWithoutAvailableModifiers($groups);

        $this->assertCount(0, $alerts);
    }

    public function test_grupo_global_disponivel_para_filial_sem_override(): void
    {
        $outraFilial = Branch::factory()->forCompany(
            Company::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'ACTIVE'])
        )->active()->create();

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->global()->create(['name' => 'Global']);

        $service = app(MenuService::class);
        $groups = $service->resolveModifierGroups($product->id, $outraFilial->id, $this->tenant->id);

        $this->assertEquals('Global', $groups->first()->name);
    }
}
