<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F2.2 — Inventory Management
 */
class InventoryWebManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private Company $company;

    private User $admin;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        $this->company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create([
            'trade_name' => 'Casa Alta',
        ]);
        $this->admin = User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Ana Admin']);
        $this->adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantAtual->id)
            ->create(['id' => (string) Str::ulid()]);

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    public function test_listagem_de_estoque_renderiza_apenas_tenant_atual_e_help(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'name' => 'Arroz Casa',
            'sku' => 'ARR-001',
        ]);
        Inventory::factory()->forProduct($product)->create(['quantity_on_hand' => 10]);

        $otherCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        $otherProduct = Product::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'company_id' => $otherCompany->id,
            'name' => 'Produto Outro Tenant',
        ]);
        Inventory::factory()->forProduct($otherProduct)->create(['quantity_on_hand' => 99]);

        $this->actingAs($this->admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('estoque')
            ->assertSee('ajuda de estoque')
            ->assertSee('Arroz Casa')
            ->assertDontSee('Produto Outro Tenant');
    }

    public function test_ajuste_de_estoque_cria_saldo_e_movimento(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'name' => 'Café Estoque',
        ]);

        $resposta = $this->actingAs($this->admin)->post(route('inventory.adjust'), [
            'product_id' => (string) $product->id,
            'company_id' => (string) $this->company->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => '12.500',
            'reason' => 'entrada inicial',
        ]);

        $inventory = Inventory::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail();

        $resposta->assertRedirect(route('inventory.show', $inventory));

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'tenant_id' => $this->tenantAtual->id,
            'quantity_on_hand' => '12.500',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => '12.500',
            'reason' => 'entrada inicial',
        ]);
    }

    public function test_niveis_de_reposicao_sao_salvos(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('inventory.stock-levels.store'), [
                'product_id' => (string) $product->id,
                'company_id' => (string) $this->company->id,
                'min_qty' => '3',
                'reorder_point' => '5',
                'max_qty' => '20',
            ])
            ->assertRedirect(route('inventory.index').'?product_id='.$product->id);

        $this->assertDatabaseHas('stock_levels', [
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'min_qty' => '3.000',
            'reorder_point' => '5.000',
            'max_qty' => '20.000',
        ]);
    }

    public function test_detalhe_mostra_historico_de_movimentos(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'name' => 'Feijão Histórico',
        ]);
        $inventory = Inventory::factory()->forProduct($product)->create(['quantity_on_hand' => 7]);
        InventoryMovement::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'inventory_id' => $inventory->id,
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity' => 7,
            'quantity_before' => 0,
            'quantity_after' => 7,
            'reason' => 'inventário físico',
        ]);

        $this->actingAs($this->admin)
            ->get(route('inventory.show', $inventory))
            ->assertOk()
            ->assertSee('Feijão Histórico')
            ->assertSee('inventário físico')
            ->assertSee('Ana Admin');
    }

    public function test_filtro_de_baixo_estoque(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'name' => 'Baixo Estoque',
        ]);
        $inventory = Inventory::factory()->forProduct($product)->create(['quantity_on_hand' => 2]);
        StockLevel::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'product_id' => $product->id,
            'company_id' => $this->company->id,
            'min_qty' => 1,
            'reorder_point' => 5,
            'max_qty' => 10,
        ]);

        $this->actingAs($this->admin)
            ->get(route('inventory.index', ['status' => 'low']))
            ->assertOk()
            ->assertSee('Baixo Estoque')
            ->assertSee($inventory->product->sku);
    }

    public function test_usuario_sem_permissao_recebe_403_no_estoque(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)
            ->get(route('inventory.index'))
            ->assertForbidden();
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        foreach (['manage-inventory', 'view-inventory'] as $permissionName) {
            $permission = Permission::factory()
                ->forTenant($tenant->id)
                ->create([
                    'name' => $permissionName,
                    'description' => $permissionName,
                ]);

            $this->adminRole->grantPermission($permission);
        }

        $user->assignRole($this->adminRole, $tenant->id);
    }
}
