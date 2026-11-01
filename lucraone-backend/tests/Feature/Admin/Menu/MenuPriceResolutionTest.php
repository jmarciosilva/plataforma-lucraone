<?php

namespace Tests\Feature\Admin\Menu;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Menu\Domain\Models\MenuSection;
use App\Modules\Menu\Domain\Models\MenuSectionItem;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rest02')]
#[Group('menu')]
class MenuPriceResolutionTest extends TestCase
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

    public function test_override_prevalece_sobre_preco_venda(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        Price::factory()->create([
            'product_id' => $product->id,
            'tenant_id' => $this->tenant->id,
            'type' => Price::TYPE_SALE,
            'amount' => '20.00',
        ]);

        $section = MenuSection::factory()->forBranch($this->filial)->create();
        $item = MenuSectionItem::factory()->forSection($section)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'price_override' => '15.00',
            'price_currency' => 'BRL',
        ]);

        $effective = $item->effectivePrice();
        $this->assertNotNull($effective);
        $this->assertEquals('15.00', $effective['amount']);
        $this->assertEquals('menu_override', $effective['source']);
    }

    public function test_sem_override_usa_preco_de_venda(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        Price::factory()->create([
            'product_id' => $product->id,
            'tenant_id' => $this->tenant->id,
            'type' => Price::TYPE_SALE,
            'amount' => '25.00',
        ]);

        $section = MenuSection::factory()->forBranch($this->filial)->create();
        $item = MenuSectionItem::factory()->forSection($section)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'price_override' => null,
            'price_currency' => null,
        ]);

        $item->load('product.prices');
        $effective = $item->effectivePrice();
        $this->assertNotNull($effective);
        $this->assertEquals('25.00', $effective['amount']);
        $this->assertEquals('product_sale_price', $effective['source']);
    }

    public function test_sem_override_e_sem_preco_de_venda_retorna_null(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $section = MenuSection::factory()->forBranch($this->filial)->create();
        $item = MenuSectionItem::factory()->forSection($section)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'price_override' => null,
            'price_currency' => null,
        ]);

        $item->load('product.prices');
        $this->assertNull($item->effectivePrice());
    }
}
