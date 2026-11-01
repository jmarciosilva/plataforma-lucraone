<?php

namespace Tests\Feature\Admin\Menu;

use App\Modules\Branches\Domain\BranchSegment;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Menu\Domain\Models\MenuSection;
use App\Modules\Menu\Domain\Models\MenuSectionItem;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\BranchResolver;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rest02')]
#[Group('menu')]
class MenuSectionItemTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $empresa;
    private Branch $filial;
    private User $admin;
    private MenuSection $section;

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

        $this->section = MenuSection::factory()->forBranch($this->filial)->create(['name' => 'Entradas']);
    }

    private function comSessao()
    {
        return $this->actingAs($this->admin)
            ->withSession([
                'tenant_ativo' => $this->tenant->id,
                BranchResolver::SESSAO_FILIAL => $this->filial->id,
            ]);
    }

    public function test_admin_adiciona_item_ao_cardapio(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->comSessao()
            ->post(route('cardapio.itens.store', $this->section), [
                'product_id' => (string) $product->id,
                'position' => 0,
                'available' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('menu_section_items', [
            'menu_section_id' => $this->section->id,
            'product_id' => (string) $product->id,
        ]);
    }

    public function test_produto_unico_por_secao(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        MenuSectionItem::factory()->forSection($this->section)->create([
            'product_id' => (string) $product->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->comSessao()
            ->post(route('cardapio.itens.store', $this->section), [
                'product_id' => (string) $product->id,
                'position' => 1,
                'available' => '1',
            ])
            ->assertSessionHasErrors();
    }

    public function test_price_override_com_moeda(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->comSessao()
            ->post(route('cardapio.itens.store', $this->section), [
                'product_id' => (string) $product->id,
                'price_override' => '29.90',
                'price_currency' => 'BRL',
                'position' => 0,
                'available' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('menu_section_items', [
            'product_id' => (string) $product->id,
            'price_override' => '29.90',
            'price_currency' => 'BRL',
        ]);
    }

    public function test_price_override_sem_moeda_falha(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->comSessao()
            ->post(route('cardapio.itens.store', $this->section), [
                'product_id' => (string) $product->id,
                'price_override' => '29.90',
                'position' => 0,
                'available' => '1',
            ])
            ->assertSessionHasErrors('price_currency');
    }

    public function test_admin_remove_item(): void
    {
        $item = MenuSectionItem::factory()->forSection($this->section)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->comSessao()
            ->delete(route('cardapio.itens.destroy', [$this->section, $item]))
            ->assertRedirect();

        $this->assertDatabaseMissing('menu_section_items', ['id' => $item->id]);
    }

    public function test_toggle_disponibilidade_item(): void
    {
        $item = MenuSectionItem::factory()->forSection($this->section)->create([
            'tenant_id' => $this->tenant->id,
            'available' => true,
        ]);

        $this->comSessao()
            ->post(route('cardapio.itens.toggle-available', [$this->section, $item]))
            ->assertRedirect();

        $this->assertDatabaseHas('menu_section_items', [
            'id' => $item->id,
            'available' => false,
        ]);
    }
}
