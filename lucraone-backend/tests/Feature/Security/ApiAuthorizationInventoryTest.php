<?php

namespace Tests\Feature\Security;

use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Products\Domain\Models\Product;

/**
 * SEC-01 — autorização das 6 rotas do módulo Inventory.
 *
 * InventoryController (5) e StockLevelController (1).
 *
 * O ajuste de estoque é autorizado por `InventoryPolicy::create` — a mesma
 * ação que o painel web exige em `AdjustWebInventoryRequest`. Nível de
 * reposição usa `StockLevelPolicy::create`, como em
 * `StoreWebStockLevelRequest`.
 */
class ApiAuthorizationInventoryTest extends ApiAuthorizationTestCase
{
    private function produto(): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => 'SEC01-INV-'.fake()->unique()->numerify('####'),
            'status' => 'active',
            'unit' => 'UN',
        ]);
    }

    // ---------------------------------------------------------------
    // Escrita barrada
    // ---------------------------------------------------------------

    public function test_leitor_nao_ajusta_estoque(): void
    {
        $produto = $this->produto();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->postJson("/api/v1/inventory/{$produto->id}/adjust", [
                'company_id' => $this->company->id,
                'type' => 'in',
                'quantity' => '10',
                'reason' => 'tentativa sem permissão',
            ])
        );

        $this->assertDatabaseMissing('inventory_movements', ['product_id' => $produto->id]);
    }

    public function test_leitor_nao_define_nivel_de_reposicao(): void
    {
        $produto = $this->produto();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->postJson("/api/v1/stock-levels/{$produto->id}", [
                'company_id' => $this->company->id,
                'min_qty' => 1,
                'max_qty' => 100,
                'reorder_point' => 5,
            ])
        );

        $this->assertDatabaseMissing('stock_levels', ['product_id' => $produto->id]);
    }

    // ---------------------------------------------------------------
    // Leitura liberada
    // ---------------------------------------------------------------

    public function test_leitor_le_estoque(): void
    {
        $produto = $this->produto();
        Inventory::factory()->forProduct($produto)->create();

        $this->comoLeitor()->getJson('/api/v1/inventory')->assertOk();
        $this->comoLeitor()->getJson('/api/v1/inventory/low-stock')->assertOk();
        $this->comoLeitor()->getJson('/api/v1/inventory/overstock')->assertOk();
        $this->comoLeitor()->getJson("/api/v1/inventory/{$produto->id}/movements")->assertOk();
    }

    // ---------------------------------------------------------------
    // Caminho positivo
    // ---------------------------------------------------------------

    public function test_gerente_ajusta_estoque(): void
    {
        $produto = $this->produto();

        $this->comoGerente()->postJson("/api/v1/inventory/{$produto->id}/adjust", [
            'company_id' => $this->company->id,
            'type' => 'in',
            'quantity' => '10',
            'reason' => 'entrada autorizada',
        ])->assertOk();

        $this->assertDatabaseHas('inventory_movements', ['product_id' => $produto->id]);
    }

    public function test_gerente_define_nivel_de_reposicao(): void
    {
        $produto = $this->produto();

        $this->comoGerente()->postJson("/api/v1/stock-levels/{$produto->id}", [
            'company_id' => $this->company->id,
            'min_qty' => 2,
            'max_qty' => 50,
            'reorder_point' => 6,
            // 201: o StockLevelResource embrulha um modelo recém-criado, e o
            // Laravel responde Created nesse caso. Comportamento preexistente.
        ])->assertCreated();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $produto->id,
            'reorder_point' => 6,
        ]);
    }

    // ---------------------------------------------------------------
    // SEC-04 — coringa
    // ---------------------------------------------------------------

    public function test_create_role_nao_autoriza_inventory(): void
    {
        $produto = $this->produto();

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->getJson('/api/v1/inventory')
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->postJson("/api/v1/inventory/{$produto->id}/adjust", [
                'company_id' => $this->company->id,
                'type' => 'in',
                'quantity' => '5',
            ])
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->postJson("/api/v1/stock-levels/{$produto->id}", [
                'company_id' => $this->company->id,
                'min_qty' => 1,
                'reorder_point' => 2,
            ])
        );

        $this->assertDatabaseMissing('inventory_movements', ['product_id' => $produto->id]);
    }

    // ---------------------------------------------------------------
    // Isolamento entre estabelecimentos
    // ---------------------------------------------------------------

    public function test_nao_ajusta_estoque_de_produto_de_outro_estabelecimento(): void
    {
        $alheio = Product::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'company_id' => $this->outraCompany->id,
            'sku' => 'SEC01-INV-ALHEIO',
            'unit' => 'UN',
        ]);

        $this->comoGerente()->postJson("/api/v1/inventory/{$alheio->id}/adjust", [
            'company_id' => $this->company->id,
            'type' => 'in',
            'quantity' => '10',
        ])->assertNotFound();

        $this->comoGerente()->postJson("/api/v1/stock-levels/{$alheio->id}", [
            'company_id' => $this->company->id,
            'min_qty' => 1,
            'reorder_point' => 2,
        ])->assertNotFound();

        $this->assertDatabaseMissing('inventory_movements', ['product_id' => $alheio->id]);
        $this->assertDatabaseMissing('stock_levels', ['product_id' => $alheio->id]);
    }

    public function test_nivel_de_reposicao_de_outro_estabelecimento_nao_vaza(): void
    {
        StockLevel::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'company_id' => $this->outraCompany->id,
        ]);

        $resposta = $this->comoLeitor()->getJson('/api/v1/inventory')->assertOk();

        $this->assertSame([], $resposta->json('data'));
    }
}
