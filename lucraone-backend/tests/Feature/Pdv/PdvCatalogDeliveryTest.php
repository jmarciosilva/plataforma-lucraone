<?php

namespace Tests\Feature\Pdv;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Pdv\Application\GeneratePdvCatalogPublication;
use App\Modules\Pdv\Domain\Models\PdvCatalogDelivery;
use App\Modules\Pdv\Domain\Models\PdvCatalogPublication;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Domain\Models\ProductPackage;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Group;

#[Group('pdv-catalog-delivery')]
class PdvCatalogDeliveryTest extends PdvTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pdv_catalog.enabled' => true]);
        $this->token = $this->credencialDeMaquina();
    }

    public function test_job_cria_snapshot_completo_versionado_e_exclui_preco_de_custo(): void
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->terminal->tenant_id,
            'company_id' => $this->terminal->company_id,
            'status' => 'active',
            'barcode' => '0000000000123',
        ]);
        Price::factory()->create(['tenant_id' => $this->terminal->tenant_id, 'product_id' => $product->id, 'type' => 'sale', 'amount' => '10.25', 'currency' => 'BRL']);
        Price::factory()->cost()->create(['tenant_id' => $this->terminal->tenant_id, 'product_id' => $product->id, 'amount' => '4.10']);
        ProductPackage::factory()->create(['tenant_id' => $this->terminal->tenant_id, 'product_id' => $product->id, 'barcode' => '0000000000456', 'factor' => 6]);
        Product::factory()->create(['tenant_id' => $this->terminal->tenant_id, 'company_id' => $this->terminal->company_id, 'status' => 'inactive']);
        $archived = Product::factory()->create(['tenant_id' => $this->terminal->tenant_id, 'company_id' => $this->terminal->company_id]);
        $archived->delete();
        $otherCompany = Company::factory()->create(['tenant_id' => $this->terminal->tenant_id]);
        Product::factory()->create(['tenant_id' => $this->terminal->tenant_id, 'company_id' => $otherCompany->id]);

        $publication = $this->publication();
        $movementsBefore = DB::table('inventory_movements')->count();
        $ordersBefore = DB::table('orders')->count();
        (new GeneratePdvCatalogPublication($publication->id))->handle();
        (new GeneratePdvCatalogPublication($publication->id))->handle();
        $publication->refresh();

        $this->assertSame(PdvCatalogPublication::PUBLISHED, $publication->status);
        $this->assertSame(1, $publication->product_count);
        $this->assertSame(1, $publication->price_count);
        $this->assertSame(1, $publication->package_count);
        $this->assertSame(64, strlen($publication->checksum));
        $payload = DB::table('pdv_catalog_snapshot_items')->where('publication_id', $publication->id)->value('payload');
        $this->assertStringContainsString('0000000000123', $payload);
        $this->assertStringContainsString('0000000000456', $payload);
        $this->assertStringContainsString('10.25', $payload);
        $this->assertStringNotContainsString('4.10', $payload);
        $this->assertStringNotContainsString('reference_cost', $payload);
        $this->assertSame(1, DB::table('pdv_catalog_snapshot_items')->where('publication_id', $publication->id)->count());
        $this->assertSame($movementsBefore, DB::table('inventory_movements')->count());
        $this->assertSame($ordersBefore, DB::table('orders')->count());
        $product->update(['name' => 'Nome alterado depois da publicação']);
        $this->assertSame($payload, DB::table('pdv_catalog_snapshot_items')->where('publication_id', $publication->id)->value('payload'));
    }

    public function test_consulta_autenticada_promove_entrega_offline_pendente(): void
    {
        $publication = $this->publicacaoPronta();
        $delivery = $this->entrega($publication, $this->terminal->id);
        $delivery->update(['status' => PdvCatalogDelivery::PENDING]);

        $this->getComMaquina('/api/v1/pdv/catalog/deliveries/pending')->assertOk()->assertJsonPath('data.0.delivery_id', $delivery->id);
        $this->assertSame(PdvCatalogDelivery::AVAILABLE, $delivery->fresh()->status);
    }

    public function test_distribuicao_fica_desativada_ate_ativacao_explicita_do_ambiente(): void
    {
        config(['pdv_catalog.enabled' => false]);

        $this->getComMaquina('/api/v1/pdv/catalog/deliveries/pending')->assertStatus(503)
            ->assertJsonPath('error.code', 'catalog_distribution_unavailable');
    }

    public function test_terminal_recebe_apenas_sua_entrega_e_pode_retomar_confirmar_e_marcar_versao_ativa(): void
    {
        $publication = $this->publicacaoPronta(['product_count' => 1]);
        $delivery = $this->entrega($publication, $this->terminal->id);
        DB::table('pdv_catalog_snapshot_items')->insert([
            'publication_id' => $publication->id, 'position' => 0,
            'payload' => '{"id":"prod-1","sku":"001","sale_prices":[],"packages":[]}',
            'checksum' => hash('sha256', '{"id":"prod-1","sku":"001","sale_prices":[],"packages":[]}'),
        ]);

        $this->getComMaquina('/api/v1/pdv/catalog/deliveries/pending')->assertOk()->assertJsonPath('data.0.delivery_id', $delivery->id);
        $this->getComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/manifest')->assertOk()->assertJsonPath('data.part_size', 200);
        $this->getComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/parts/0')->assertOk()
            ->assertHeader('Content-Type', 'application/x-ndjson; charset=utf-8')
            ->assertHeader('X-PDV-Catalog-Part', '0')->assertSeeText('"sku":"001"');
        $this->postComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/progress', ['last_part' => 0])->assertOk();
        $this->postComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/received')->assertOk()->assertJsonPath('data.status', PdvCatalogDelivery::RECEIVED);
        $this->postComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/applied')->assertOk()->assertJsonPath('data.version', 1);
        $this->assertDatabaseHas('pdv_terminal_catalog_states', ['terminal_id' => $this->terminal->id, 'version' => 1]);
        $this->postComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/applied')->assertOk();
    }

    public function test_ability_e_destinatario_impedem_acesso_de_credencial_legada_e_de_outro_terminal(): void
    {
        $publication = $this->publicacaoPronta();
        $otherTerminal = Terminal::factory()->create([
            'tenant_id' => $this->terminal->tenant_id, 'company_id' => $this->terminal->company_id,
            'branch_id' => $this->terminal->branch_id, 'installation_id' => (string) Str::uuid(), 'status' => 'ACTIVE',
        ]);
        $delivery = $this->entrega($publication, $otherTerminal->id);

        $this->getComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/manifest')->assertNotFound();
        $legacyAbilities = [
            MachineTokenAbility::TERMINAL_READ,
            MachineTokenAbility::CREDENTIAL_RENEW,
            MachineTokenAbility::CREDENTIAL_CONFIRM,
            MachineTokenAbility::OPERATOR_SESSION,
            MachineTokenAbility::CATALOG_READ,
        ];
        $legacy = $this->terminal->createToken('legacy-before-catalog-delivery', $legacyAbilities)->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($legacy)->getJson('/api/v1/pdv/catalog/deliveries/pending')->assertForbidden();
        $this->assertNotContains(MachineTokenAbility::CATALOG_DISTRIBUTION, PersonalAccessToken::findToken($legacy)->abilities);
    }

    public function test_checkpoint_nao_pula_partes_e_terminal_bloqueado_nao_recebe_cargas(): void
    {
        $publication = $this->publicacaoPronta(['product_count' => 201]);
        $delivery = $this->entrega($publication, $this->terminal->id);
        $this->postComMaquina('/api/v1/pdv/catalog/deliveries/'.$delivery->id.'/progress', ['last_part' => 1])->assertStatus(409);
        $this->terminal->update(['status' => 'BLOCKED']);
        $this->getComMaquina('/api/v1/pdv/catalog/deliveries/pending')->assertUnauthorized();
    }

    private function publication(): PdvCatalogPublication
    {
        return PdvCatalogPublication::query()->create([
            'id' => (string) Str::ulid(), 'tenant_id' => $this->terminal->tenant_id,
            'company_id' => $this->terminal->company_id, 'version' => 1, 'schema_version' => 1,
            'status' => PdvCatalogPublication::GENERATING,
        ]);
    }

    private function publicacaoPronta(array $overrides = []): PdvCatalogPublication
    {
        return PdvCatalogPublication::query()->create(array_merge([
            'id' => (string) Str::ulid(), 'tenant_id' => $this->terminal->tenant_id,
            'company_id' => $this->terminal->company_id, 'version' => 1, 'schema_version' => 1,
            'status' => PdvCatalogPublication::PUBLISHED, 'checksum' => str_repeat('a', 64),
            'product_count' => 0, 'price_count' => 0, 'package_count' => 0,
        ], $overrides));
    }

    private function entrega(PdvCatalogPublication $publication, string $terminalId): PdvCatalogDelivery
    {
        return PdvCatalogDelivery::query()->create([
            'id' => (string) Str::ulid(), 'publication_id' => $publication->id, 'terminal_id' => $terminalId,
            'status' => PdvCatalogDelivery::AVAILABLE,
        ]);
    }

    private function getComMaquina(string $url)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->getJson($url);
    }

    private function postComMaquina(string $url, array $payload = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->postJson($url, $payload);
    }
}
