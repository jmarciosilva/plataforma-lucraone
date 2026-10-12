<?php

namespace Tests\Integration\Pdv;

use App\Modules\Pdv\Application\CatalogDeliveryLifecycle;
use App\Modules\Pdv\Domain\Models\PdvCatalogDelivery;
use App\Modules\Pdv\Domain\Models\PdvCatalogPublication;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\ConcurrencyTestCase;

/** Exercises the applied/failed row-lock race using independent MySQL processes. */
class CatalogDeliveryConcurrencyTest extends ConcurrencyTestCase
{
    use DatabaseMigrations;

    public function test_applied_and_failed_concurrent_calls_cannot_overwrite_a_confirmed_application(): void
    {
        $terminal = Terminal::factory()->create([
            'status' => Terminal::STATUS_ACTIVE,
            'installation_id' => (string) Str::uuid(),
        ]);
        $publication = PdvCatalogPublication::query()->create([
            'id' => (string) Str::ulid(), 'tenant_id' => $terminal->tenant_id,
            'company_id' => $terminal->company_id, 'version' => 1, 'schema_version' => 1,
            'status' => PdvCatalogPublication::PUBLISHED, 'product_count' => 0,
            'price_count' => 0, 'package_count' => 0, 'checksum' => hash('sha256', ''),
        ]);
        $delivery = PdvCatalogDelivery::query()->create([
            'id' => (string) Str::ulid(), 'publication_id' => $publication->id,
            'terminal_id' => $terminal->id, 'status' => PdvCatalogDelivery::RECEIVED,
            'manifest_seen_at' => now(), 'transfer_verified_at' => now(), 'received_at' => now(),
        ]);
        $terminalId = $terminal->id;
        $deliveryId = $delivery->id;

        $results = $this->race([
            fn () => $this->transition('applied', $terminalId, $deliveryId),
            fn () => $this->transition('failed', $terminalId, $deliveryId),
        ]);

        $this->assertCount(2, $results);
        $successful = array_values(array_filter($results, fn ($result) => $result['result']['successful']));
        $this->assertCount(1, $successful);
        $expectedStatus = $successful[0]['result']['transition'] === 'applied' ? PdvCatalogDelivery::APPLIED : PdvCatalogDelivery::FAILED;
        $this->assertSame($expectedStatus, PdvCatalogDelivery::query()->whereKey($deliveryId)->value('status'));
        $this->assertSame($expectedStatus === PdvCatalogDelivery::APPLIED ? 1 : 0,
            DB::table('pdv_terminal_catalog_states')->where('terminal_id', $terminalId)->count());
        $this->assertContains($results[array_search(false, array_column(array_column($results, 'result'), 'successful'), true)]['result']['errorCode'], [
            'catalog_apply_conflict', 'catalog_delivery_state_conflict',
        ]);
    }

    private function transition(string $transition, string $terminalId, string $deliveryId): array
    {
        $result = $transition === 'applied'
            ? app(CatalogDeliveryLifecycle::class)->applied($terminalId, $deliveryId)
            : app(CatalogDeliveryLifecycle::class)->failed($terminalId, $deliveryId, 'APPLY_FAILED');

        return ['transition' => $transition, 'successful' => $result->successful(), 'errorCode' => $result->errorCode];
    }
}
