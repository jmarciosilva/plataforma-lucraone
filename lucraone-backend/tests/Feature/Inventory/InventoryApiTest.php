<?php

namespace Tests\Feature\Inventory;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private Company $company;

    private Product $product;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->outroTenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => 'INV-001',
        ]);
        $this->user = User::factory()->forTenant($this->tenant)->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_adjust_inventory_creates_balance_and_movement(): void
    {
        $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/inventory/{$this->product->id}/adjust", [
                'company_id' => $this->company->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => 15.5,
                'reason' => 'compra inicial',
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity_on_hand', '15.500')
            ->assertJsonPath('data.available', '15.500');

        $inventory = Inventory::withoutGlobalScopes()->where('product_id', $this->product->id)->firstOrFail();

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $this->tenant->id,
            'inventory_id' => $inventory->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => '15.500',
            'quantity_before' => '0.000',
            'quantity_after' => '15.500',
            'reason' => 'compra inicial',
        ]);
    }

    public function test_out_movement_cannot_make_quantity_negative(): void
    {
        $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/inventory/{$this->product->id}/adjust", [
                'company_id' => $this->company->id,
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => 1,
                'reason' => 'perda',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'saída maior que o saldo em mãos.');
    }

    public function test_stock_levels_and_alerts_work(): void
    {
        $inventory = Inventory::factory()->forProduct($this->product)->create([
            'quantity_on_hand' => 3,
            'reserved' => 0,
        ]);

        $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson("/api/v1/stock-levels/{$this->product->id}", [
                'company_id' => $this->company->id,
                'min_qty' => 2,
                'reorder_point' => 5,
                'max_qty' => 20,
            ])
            ->assertCreated()
            ->assertJsonPath('data.reorder_point', '5.000');

        $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/inventory/low-stock')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $inventory->id);

        $inventory->update(['quantity_on_hand' => 30]);

        $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/inventory/overstock')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $inventory->id);
    }

    public function test_movements_endpoint_returns_history(): void
    {
        $inventory = Inventory::factory()->forProduct($this->product)->create(['quantity_on_hand' => 10]);
        InventoryMovement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'inventory_id' => $inventory->id,
            'product_id' => $this->product->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
        ]);

        $this->withToken($this->token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson("/api/v1/inventory/{$this->product->id}/movements")
            ->assertOk()
            ->assertJsonPath('data.0.type', InventoryMovement::TYPE_IN);
    }

    public function test_inventory_index_is_isolated_by_tenant(): void
    {
        $inventory = Inventory::factory()->forProduct($this->product)->create(['quantity_on_hand' => 10]);

        $otherCompany = Company::factory()->forCurrentTenant($this->outroTenant->id)->active()->create();
        $otherProduct = Product::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'company_id' => $otherCompany->id,
        ]);
        Inventory::factory()->forProduct($otherProduct)->create(['quantity_on_hand' => 99]);

        $ids = collect(
            $this->withToken($this->token)
                ->withHeader('X-Tenant-ID', $this->tenant->id)
                ->getJson('/api/v1/inventory')
                ->assertOk()
                ->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($inventory->id));
        $this->assertCount(1, $ids);
    }
}
