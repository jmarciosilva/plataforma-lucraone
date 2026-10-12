<?php

namespace Tests\Feature\Pdv;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Sales\Domain\Models\OrderItem;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Group;

#[Group('pdv-catalog')]
class PdvCatalogApiTest extends PdvOperatorTestCase
{
    private function catalogo(string $sessao): TestResponse
    {
        return $this->comSessao('GET', '/api/v1/pdv/catalog/products', $sessao);
    }

    private function produto(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->terminal->company_id,
            'barcode' => null,
        ], $attrs));
    }

    public function test_catalogo_exige_duas_identidades_e_filtra_escopo_da_empresa(): void
    {
        $own = $this->produto(['sku' => 'SKU-OWN', 'name' => 'Arroz Branco']);
        $otherCompany = Company::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->produto(['company_id' => $otherCompany->id, 'sku' => 'SKU-OTHER']);
        $otherTenant = Product::factory()->create(['sku' => 'SKU-TENANT']);
        $this->produto(['status' => 'inactive', 'sku' => 'SKU-INACTIVE']);
        $archived = $this->produto(['sku' => 'SKU-ARCHIVED']);
        $archived->delete();

        $session = $this->abrirSessao();
        $response = $this->catalogo($session)->assertOk();

        $this->assertSame([(string) $own->id], array_column($response->json('data'), 'id'));
        $response->assertJsonMissing(['sku' => 'SKU-OTHER'])
            ->assertJsonMissing(['sku' => 'SKU-TENANT'])
            ->assertJsonMissing(['sku' => 'SKU-INACTIVE'])
            ->assertJsonMissing(['sku' => 'SKU-ARCHIVED']);
        $this->assertNotSame($this->tenant->id, $otherTenant->tenant_id);
    }

    public function test_catalogo_expoe_apenas_precos_de_venda_com_decimal_string_e_embalagens(): void
    {
        $product = $this->produto(['sku' => 'ARROZ-5', 'barcode' => '0000000012345', 'unit' => 'KG']);
        Price::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'amount' => '12.30', 'currency' => 'BRL', 'type' => 'sale']);
        Price::factory()->cost()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'amount' => '7.00']);
        $package = ProductPackage::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'barcode' => '0000000098765', 'factor' => 6]);
        $session = $this->abrirSessao();

        $this->catalogo($session)
            ->assertOk()
            ->assertJsonPath('data.0.barcode', '0000000012345')
            ->assertJsonPath('data.0.unit', 'KG')
            ->assertJsonPath('data.0.sale_prices.0.amount', '12.30')
            ->assertJsonPath('data.0.sale_prices.0.currency', 'BRL')
            ->assertJsonPath('data.0.packages.0.id', (string) $package->id)
            ->assertJsonMissing(['amount' => '7.00'])
            ->assertJsonMissingPath('data.0.company_id');
    }

    public function test_produto_sem_preco_representa_lista_vazia_e_busca_por_nome_ou_sku(): void
    {
        $this->produto(['sku' => 'LEITE-001', 'name' => 'Leite Integral']);
        $session = $this->abrirSessao();

        $this->comSessao('GET', '/api/v1/pdv/catalog/products?search=Leite', $session)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sale_prices', []);
        $this->comSessao('GET', '/api/v1/pdv/catalog/products?search=LEITE-001', $session)
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_paginacao_cursor_tem_ordem_deterministica_e_limite(): void
    {
        $items = collect(['A', 'B', 'C'])->map(fn ($sku) => $this->produto(['sku' => $sku]));
        $session = $this->abrirSessao();

        $first = $this->comSessao('GET', '/api/v1/pdv/catalog/products?per_page=2', $session)->assertOk();
        $this->assertCount(2, $first->json('data'));
        $this->assertTrue($first->json('meta.has_more'));
        $cursor = $first->json('meta.next_cursor');
        $second = $this->comSessao('GET', '/api/v1/pdv/catalog/products?per_page=2&cursor='.urlencode($cursor), $session)->assertOk();
        $this->assertCount(1, $second->json('data'));
        $this->assertFalse($second->json('meta.has_more'));
        $this->assertSame((string) $items->sortBy('id')->last()->id, $second->json('data.0.id'));
    }

    public function test_filtros_invalidos_e_cursor_malformado_retorna_422(): void
    {
        $session = $this->abrirSessao();
        $this->comSessao('GET', '/api/v1/pdv/catalog/products?per_page=101', $session)
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
        $this->comSessao('GET', '/api/v1/pdv/catalog/products?cursor=not-a-cursor', $session)
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_error');
    }

    public function test_resolve_codigo_de_produto_e_embalagem_reutiliza_resolvedor_sem_mutar_operacao(): void
    {
        $product = $this->produto(['barcode' => '0001234567890']);
        $package = ProductPackage::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'barcode' => '0009876543210', 'factor' => 12]);
        $session = $this->abrirSessao();
        $beforeMovements = InventoryMovement::query()->count();
        $beforeItems = OrderItem::query()->count();

        $this->comSessao('GET', '/api/v1/pdv/catalog/resolve-barcode/0001234567890', $session)
            ->assertOk()->assertJsonPath('data.source', 'product')->assertJsonPath('data.quantity', 1);
        $this->comSessao('GET', '/api/v1/pdv/catalog/resolve-barcode/0009876543210', $session)
            ->assertOk()->assertJsonPath('data.package.id', (string) $package->id)->assertJsonPath('data.quantity', 12);
        $this->assertSame($beforeMovements, InventoryMovement::query()->count());
        $this->assertSame($beforeItems, OrderItem::query()->count());
    }

    public function test_barcode_de_outra_empresa_nao_e_revelado_e_inexistente_e_404(): void
    {
        $otherCompany = Company::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->produto(['company_id' => $otherCompany->id, 'barcode' => '0000000000777']);
        $session = $this->abrirSessao();

        $this->comSessao('GET', '/api/v1/pdv/catalog/resolve-barcode/0000000000777', $session)
            ->assertNotFound()->assertJsonPath('error.code', 'product_not_found');
        $this->comSessao('GET', '/api/v1/pdv/catalog/resolve-barcode/0000000000778', $session)
            ->assertNotFound()->assertJsonPath('error.code', 'product_not_found');
    }

    public function test_barcode_em_conflito_na_propria_empresa_retorna_409(): void
    {
        $product = $this->produto(['barcode' => '0000000000666']);
        ProductPackage::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'barcode' => '0000000000666']);
        $session = $this->abrirSessao();

        $this->comSessao('GET', '/api/v1/pdv/catalog/resolve-barcode/0000000000666', $session)
            ->assertConflict()->assertJsonPath('error.code', 'barcode_conflict');
    }

    public function test_sem_operador_e_credencial_legada_nao_acessam_catalogo(): void
    {
        $this->novaRequisicao();
        $this->withToken($this->maquina)->getJson('/api/v1/pdv/catalog/products')
            ->assertUnauthorized()->assertJsonPath('error.code', 'operator_session_invalid');

        $legacy = $this->terminal->createToken('old-machine', [MachineTokenAbility::OPERATOR_SESSION])->plainTextToken;
        $session = $this->abrirSessao($legacy);
        $this->comSessao('GET', '/api/v1/pdv/catalog/products', $session, $legacy)
            ->assertForbidden();
        $this->assertNotContains(MachineTokenAbility::CATALOG_READ, PersonalAccessToken::findToken($legacy)->abilities);
    }

    public function test_headers_de_sessao_em_query_sao_recusados_e_rate_limit_e_configurado(): void
    {
        $session = $this->abrirSessao();
        $this->novaRequisicao();
        $this->withToken($this->maquina)
            ->getJson('/api/v1/pdv/catalog/products?operator_session=secret')
            ->assertForbidden();
        $this->assertNotNull(app('router')->getRoutes()->getByName('pdv.catalog.products'));
    }

    public function test_terminal_bloqueado_nao_consulta_catalogo(): void
    {
        $session = $this->abrirSessao();
        $this->terminal->update(['status' => 'BLOCKED']);

        $this->comSessao('GET', '/api/v1/pdv/catalog/products', $session)
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_terminal_revogado_nao_consulta_catalogo(): void
    {
        $session = $this->abrirSessao();
        $this->terminal->update(['status' => 'REVOKED']);

        $this->comSessao('GET', '/api/v1/pdv/catalog/products', $session)
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_sessao_perde_acesso_apos_revogacao_da_atribuicao_a_filial(): void
    {
        $session = $this->abrirSessao();
        PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->operador->id)
            ->where('branch_id', $this->branch->id)
            ->update(['status' => PdvOperatorBranchAssignment::STATUS_REVOKED, 'revoked_at' => now()]);

        $this->comSessao('GET', '/api/v1/pdv/catalog/products', $session)
            ->assertUnauthorized()->assertJsonPath('error.code', 'operator_session_invalid');
    }

    public function test_cabecalho_de_selecao_de_tenant_e_recusado(): void
    {
        $session = $this->abrirSessao();

        $this->comSessao('GET', '/api/v1/pdv/catalog/products', $session, cabecalhos: ['X-Tenant-ID' => $this->tenant->id])
            ->assertForbidden()->assertJsonPath('error.code', 'forbidden');
    }

    public function test_catalogo_aplica_limite_de_120_requisicoes_por_minuto(): void
    {
        $session = $this->abrirSessao();
        for ($index = 0; $index < 120; $index++) {
            $this->comSessao('GET', '/api/v1/pdv/catalog/products?per_page=1', $session)->assertOk();
        }

        $this->comSessao('GET', '/api/v1/pdv/catalog/products?per_page=1', $session)
            ->assertTooManyRequests()->assertJsonPath('error.code', 'rate_limited');
    }

    public function test_erros_de_catalogo_nao_registram_headers_ou_credenciais(): void
    {
        Log::spy();
        $session = $this->abrirSessao();
        $this->comSessao('GET', '/api/v1/pdv/catalog/resolve-barcode/0000000000888', $session)->assertNotFound();
        foreach (['info', 'debug', 'warning', 'error', 'critical', 'log'] as $method) {
            Log::shouldNotHaveReceived($method);
        }
    }
}
