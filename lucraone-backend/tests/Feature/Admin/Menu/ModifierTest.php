<?php

namespace Tests\Feature\Admin\Menu;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
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
class ModifierTest extends TestCase
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

    public function test_modifier_tem_price_delta(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->create(['type' => ModifierGroupType::ADDITIONAL]);
        $modifier = Modifier::factory()->forGroup($group)->create(['price_delta' => '5.50']);

        $this->assertEquals('5.50', $modifier->price_delta);
    }

    public function test_modifier_is_removal_retorna_true_para_grupo_removal(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->removal()->create();
        $modifier = Modifier::factory()->forGroup($group)->free()->create();

        $this->assertTrue($modifier->isRemoval());
    }

    public function test_modifier_is_removal_retorna_false_para_grupo_adicional(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->create(['type' => ModifierGroupType::ADDITIONAL]);
        $modifier = Modifier::factory()->forGroup($group)->create();

        $this->assertFalse($modifier->isRemoval());
    }

    public function test_modifier_disponivel_por_default(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->create();
        $modifier = Modifier::factory()->forGroup($group)->create();

        $this->assertTrue($modifier->available);
    }

    public function test_modifier_pode_ser_indisponibilizado(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->create();
        $modifier = Modifier::factory()->forGroup($group)->create(['available' => true]);

        $modifier->update(['available' => false]);
        $modifier->refresh();

        $this->assertFalse($modifier->available);
    }

    public function test_group_has_available_modifiers_retorna_false_quando_todos_indisponiveis(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->create();
        Modifier::factory()->forGroup($group)->unavailable()->create();
        Modifier::factory()->forGroup($group)->unavailable()->create();

        $group->load('modifiers');
        $this->assertFalse($group->hasAvailableModifiers());
    }

    public function test_group_has_available_modifiers_retorna_true_quando_ao_menos_um_disponivel(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = ModifierGroup::factory()->forProduct($product)->create();
        Modifier::factory()->forGroup($group)->unavailable()->create();
        Modifier::factory()->forGroup($group)->create(['available' => true]);

        $group->load('modifiers');
        $this->assertTrue($group->hasAvailableModifiers());
    }
}
