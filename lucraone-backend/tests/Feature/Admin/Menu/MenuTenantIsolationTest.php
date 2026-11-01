<?php

namespace Tests\Feature\Admin\Menu;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Menu\Application\MenuService;
use App\Modules\Menu\Domain\Models\ModifierGroup;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rest02')]
#[Group('menu')]
class MenuTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Branch $filialA;
    private Branch $filialB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->active()->create(['name' => 'Restaurante A']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenantA);

        $this->tenantB = Tenant::factory()->active()->create(['name' => 'Restaurante B']);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenantB);

        $empresaA = Company::factory()->create(['tenant_id' => $this->tenantA->id, 'status' => 'ACTIVE']);
        $empresaB = Company::factory()->create(['tenant_id' => $this->tenantB->id, 'status' => 'ACTIVE']);

        $this->filialA = Branch::factory()->forCompany($empresaA)->active()->create();
        $this->filialB = Branch::factory()->forCompany($empresaB)->active()->create();
    }

    public function test_servico_nao_retorna_grupos_de_outro_tenant(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);
        $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);

        ModifierGroup::factory()->forProduct($productA)->global()->create([
            'name' => 'Grupo Tenant A',
            'tenant_id' => $this->tenantA->id,
        ]);
        ModifierGroup::factory()->forProduct($productB)->global()->create([
            'name' => 'Grupo Tenant B',
            'tenant_id' => $this->tenantB->id,
        ]);

        $service = app(MenuService::class);

        $gruposA = $service->resolveModifierGroups($productA->id, $this->filialA->id, $this->tenantA->id);
        $gruposB = $service->resolveModifierGroups($productB->id, $this->filialB->id, $this->tenantB->id);

        $this->assertCount(1, $gruposA);
        $this->assertEquals('Grupo Tenant A', $gruposA->first()->name);

        $this->assertCount(1, $gruposB);
        $this->assertEquals('Grupo Tenant B', $gruposB->first()->name);
    }

    public function test_has_branch_override_isolado_por_tenant(): void
    {
        $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);
        ModifierGroup::factory()->forProduct($productA)->forBranch($this->filialA)->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        $service = app(MenuService::class);

        $this->assertTrue($service->hasBranchOverride($productA->id, $this->filialA->id, $this->tenantA->id));
        $this->assertFalse($service->hasBranchOverride($productA->id, $this->filialB->id, $this->tenantB->id));
    }
}
