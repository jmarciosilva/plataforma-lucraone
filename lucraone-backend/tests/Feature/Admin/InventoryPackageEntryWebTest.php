<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PM-02B — entrada e saída de estoque informadas em embalagens.
 *
 * Com embalagem, `quantity` é o número de embalagens; o estoque recebe
 * quantity × factor na unidade base do produto. Sem embalagem, nada muda.
 */
class InventoryPackageEntryWebTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private Company $company;

    private User $admin;

    private Product $lata;

    private ProductPackage $caixa24;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        $this->company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create();
        $this->admin = User::factory()->forTenant($this->tenantAtual)->create();
        $this->tornarAdmin($this->admin, $this->tenantAtual);

        $this->lata = $this->produto(['name' => 'Coca-Cola 350 ml']);
        $this->caixa24 = $this->embalagem($this->lata, 'Caixa 24', 24);
    }

    // --- sem embalagem: comportamento preservado -------------------------

    public function test_entrada_e_saida_sem_embalagem_continuam_iguais(): void
    {
        $this->movimentar($this->lata, 'in', '5')->assertSessionHasNoErrors();
        $this->movimentar($this->lata, 'out', '2')->assertSessionHasNoErrors();

        $this->assertSaldo($this->lata, 3.0);
        $this->assertSame([5.0, 2.0], $this->quantidadesMovimentadas($this->lata));
    }

    public function test_produto_kg_sem_embalagem_continua_aceitando_decimal(): void
    {
        $massa = $this->produto(['name' => 'Massa fresca', 'unit' => 'KG']);

        $this->movimentar($massa, 'in', '0.350')->assertSessionHasNoErrors();

        $this->assertSaldo($massa, 0.35);
    }

    public function test_ajuste_absoluto_sem_embalagem_continua_igual(): void
    {
        $this->movimentar($this->lata, 'in', '5');
        $this->movimentar($this->lata, 'adjustment', '10')->assertSessionHasNoErrors();

        $this->assertSaldo($this->lata, 10.0);
    }

    // --- conversão ---------------------------------------------------------

    public function test_uma_caixa_24_gera_24_unidades(): void
    {
        $this->movimentar($this->lata, 'in', '1', $this->caixa24)->assertSessionHasNoErrors();

        $this->assertSaldo($this->lata, 24.0);
    }

    public function test_tres_fardos_6_geram_18_unidades(): void
    {
        $garrafa = $this->produto(['name' => 'Refrigerante 2 L']);
        $fardo = $this->embalagem($garrafa, 'Fardo 6', 6);

        $this->movimentar($garrafa, 'in', '3', $fardo)->assertSessionHasNoErrors();

        $this->assertSaldo($garrafa, 18.0);
    }

    public function test_entrada_e_saida_por_caixa_gravam_quantidade_base_no_movimento_e_no_saldo(): void
    {
        $this->movimentar($this->lata, 'in', '10')->assertSessionHasNoErrors();

        $this->movimentar($this->lata, 'in', '2', $this->caixa24, 'compra fornecedor')
            ->assertSessionHasNoErrors();
        $this->assertSaldo($this->lata, 58.0);

        $this->movimentar($this->lata, 'out', '1', $this->caixa24)->assertSessionHasNoErrors();
        $this->assertSaldo($this->lata, 34.0);

        $this->assertSame([10.0, 48.0, 24.0], $this->quantidadesMovimentadas($this->lata));

        $entrada = $this->movimentos($this->lata)->get(1);
        $this->assertSame(InventoryMovement::TYPE_IN, $entrada->type);
        $this->assertSame(10.0, (float) $entrada->quantity_before);
        $this->assertSame(58.0, (float) $entrada->quantity_after);
    }

    public function test_motivo_registra_a_conversao_e_preserva_o_texto_do_usuario(): void
    {
        $this->movimentar($this->lata, 'in', '2', $this->caixa24, 'compra fornecedor');
        $this->movimentar($this->lata, 'out', '1', $this->caixa24);

        [$entrada, $saida] = $this->movimentos($this->lata)->all();

        $this->assertStringContainsString('2 × Caixa 24 = 48 UN', $entrada->reason);
        $this->assertStringContainsString('compra fornecedor', $entrada->reason);
        $this->assertStringContainsString('1 × Caixa 24 = 24 UN', $saida->reason);
    }

    public function test_motivo_composto_respeita_o_limite_da_coluna(): void
    {
        $caixaNomeLongo = $this->embalagem($this->lata, str_repeat('n', 100), 12);

        $this->movimentar($this->lata, 'in', '1', $caixaNomeLongo, str_repeat('m', 255))
            ->assertSessionHasNoErrors();

        $this->assertLessThanOrEqual(255, mb_strlen($this->movimentos($this->lata)->first()->reason));
        $this->assertSaldo($this->lata, 12.0);
    }

    // --- recusas ---------------------------------------------------------

    public function test_embalagem_de_outro_produto_e_recusada(): void
    {
        $garrafa = $this->produto();
        $fardo = $this->embalagem($garrafa, 'Fardo 6', 6);

        $this->movimentar($this->lata, 'in', '1', $fardo)
            ->assertSessionHasErrors(['package_id' => 'a embalagem não pertence a este produto.']);

        $this->assertSemMovimento();
    }

    public function test_embalagem_de_outro_tenant_e_recusada(): void
    {
        $produtoAlheio = Product::factory()->create(['tenant_id' => $this->outroTenant->id]);
        $caixaAlheia = ProductPackage::factory()->create([
            'tenant_id' => $this->outroTenant->id,
            'product_id' => $produtoAlheio->id,
        ]);

        $this->movimentar($this->lata, 'in', '1', $caixaAlheia)
            ->assertSessionHasErrors(['package_id' => 'a embalagem não pertence a este produto.']);

        $this->assertSemMovimento();
    }

    public function test_embalagem_em_produto_kg_e_recusada(): void
    {
        // Embalagem criada quando o produto era UN, e a unidade mudou depois.
        $this->lata->update(['unit' => 'KG']);

        $this->movimentar($this->lata, 'in', '1', $this->caixa24)
            ->assertSessionHasErrors(['package_id' => 'embalagem indisponível para produto vendido em KG.']);

        $this->assertSemMovimento();
    }

    public function test_embalagem_so_vale_para_entrada_e_saida(): void
    {
        foreach (['adjustment', 'reservation', 'release'] as $tipo) {
            $this->movimentar($this->lata, $tipo, '1', $this->caixa24)
                ->assertSessionHasErrors(['package_id' => 'embalagem só pode ser usada em entrada ou saída, não em ajuste absoluto, reserva ou liberação.']);
        }

        $this->assertSemMovimento();
    }

    public function test_quantidade_de_embalagens_precisa_ser_inteira_e_positiva(): void
    {
        foreach (['1.5', '0.5', '0', '-2', 'duas'] as $quantidade) {
            $this->movimentar($this->lata, 'in', $quantidade, $this->caixa24)
                ->assertSessionHasErrors(['quantity' => 'a quantidade de embalagens deve ser inteira e maior ou igual a 1.']);
        }

        $this->assertSemMovimento();
    }

    public function test_embalagem_removida_nao_pode_ser_usada(): void
    {
        $id = (string) $this->caixa24->id;
        $this->caixa24->delete();

        $this->actingAs($this->admin)
            ->post(route('inventory.adjust'), [
                'product_id' => (string) $this->lata->id,
                'company_id' => (string) $this->company->id,
                'type' => 'in',
                'quantity' => '1',
                'package_id' => $id,
            ])
            ->assertSessionHasErrors(['package_id' => 'a embalagem não pertence a este produto.']);

        $this->assertSemMovimento();
    }

    // --- formulário ------------------------------------------------------

    public function test_formulario_oferece_apenas_embalagens_de_produtos_un_do_tenant(): void
    {
        $massa = $this->produto(['name' => 'Massa fresca', 'unit' => 'KG']);
        $produtoAlheio = Product::factory()->create(['tenant_id' => $this->outroTenant->id]);
        ProductPackage::factory()->create(['tenant_id' => $this->outroTenant->id, 'product_id' => $produtoAlheio->id]);

        $resposta = $this->actingAs($this->admin)
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('embalagem');

        $opcoes = $resposta->viewData('packageOptions');

        $this->assertSame(
            [['id' => (string) $this->caixa24->id, 'name' => 'Caixa 24', 'factor' => 24]],
            $opcoes[(string) $this->lata->id]
        );
        $this->assertArrayNotHasKey((string) $massa->id, $opcoes);
        $this->assertArrayNotHasKey((string) $produtoAlheio->id, $opcoes);
    }

    // --- apoio -----------------------------------------------------------

    private function movimentar(Product $product, string $tipo, string $quantidade, ?ProductPackage $package = null, ?string $motivo = null)
    {
        // Um segundo entre movimentos deixa a ordem por moved_at determinística.
        $this->travel(1)->seconds();

        return $this->actingAs($this->admin)
            ->from(route('inventory.index'))
            ->post(route('inventory.adjust'), array_filter([
                'product_id' => (string) $product->id,
                'company_id' => (string) $this->company->id,
                'type' => $tipo,
                'quantity' => $quantidade,
                'package_id' => $package ? (string) $package->id : null,
                'reason' => $motivo,
            ], fn ($valor) => $valor !== null));
    }

    private function assertSaldo(Product $product, float $esperado): void
    {
        $inventory = Inventory::withoutGlobalScopes()->where('product_id', $product->id)->firstOrFail();

        $this->assertSame($esperado, (float) $inventory->quantity_on_hand);
    }

    private function movimentos(Product $product)
    {
        return InventoryMovement::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get()
            ->values();
    }

    private function quantidadesMovimentadas(Product $product): array
    {
        return $this->movimentos($product)->map(fn ($movimento) => (float) $movimento->quantity)->all();
    }

    private function assertSemMovimento(): void
    {
        $this->assertSame(0, InventoryMovement::withoutGlobalScopes()->count());
    }

    private function produto(array $atributos = []): Product
    {
        return Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            ...$atributos,
        ]);
    }

    private function embalagem(Product $product, string $nome, int $fator): ProductPackage
    {
        return ProductPackage::factory()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'name' => $nome,
            'factor' => $fator,
        ]);
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        $role = Role::factory()->admin()->forTenant($tenant->id)->create(['id' => (string) Str::ulid()]);

        foreach (['manage-inventory', 'view-inventory'] as $permissionName) {
            $role->grantPermission(Permission::factory()->forTenant($tenant->id)->create([
                'name' => $permissionName,
                'description' => $permissionName,
            ]));
        }

        $user->assignRole($role, $tenant->id);
    }
}
