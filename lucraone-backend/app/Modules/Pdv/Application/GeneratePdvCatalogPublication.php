<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Pdv\Domain\Models\PdvCatalogDelivery;
use App\Modules\Pdv\Domain\Models\PdvCatalogPublication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GeneratePdvCatalogPublication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    private const ONLINE_SECONDS = 90;

    public function __construct(public readonly string $publicationId) {}

    public function handle(): void
    {
        $publication = PdvCatalogPublication::query()->findOrFail($this->publicationId);
        if ($publication->status !== PdvCatalogPublication::GENERATING) {
            return;
        }

        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            DB::transaction(function () use ($publication): void {
                /** @var PdvCatalogPublication $publication */
                $publication = PdvCatalogPublication::query()->whereKey($publication->id)->lockForUpdate()->firstOrFail();
                if ($publication->status !== PdvCatalogPublication::GENERATING) {
                    return;
                }
                $hash = hash_init('sha256');
                $position = 0;
                $prices = 0;
                $packages = 0;

                DB::table('products')
                    ->where('tenant_id', $publication->tenant_id)
                    ->where('company_id', $publication->company_id)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->chunkById(200, function ($products) use ($publication, &$hash, &$position, &$prices, &$packages): void {
                        $batch = [];
                        $ids = $products->pluck('id')->all();
                        $salePrices = DB::table('prices')->where('tenant_id', $publication->tenant_id)
                            ->whereIn('product_id', $ids)->where('type', 'sale')->orderBy('product_id')->orderBy('currency')->get()
                            ->groupBy('product_id');
                        $productPackages = DB::table('product_packages')->where('tenant_id', $publication->tenant_id)
                            ->whereIn('product_id', $ids)->orderBy('product_id')->orderBy('id')->get()->groupBy('product_id');

                        foreach ($products as $product) {
                            $productPrices = ($salePrices->get($product->id) ?? collect())->map(fn ($price) => [
                                'amount' => (string) $price->amount,
                                'currency' => (string) $price->currency,
                            ])->values()->all();
                            $productPackagesList = ($productPackages->get($product->id) ?? collect())->map(fn ($package) => [
                                'id' => (string) $package->id,
                                'name' => (string) $package->name,
                                'barcode' => $package->barcode,
                                'factor' => (int) $package->factor,
                            ])->values()->all();

                            $payload = [
                                'id' => (string) $product->id,
                                'sku' => $product->sku,
                                'name' => (string) $product->name,
                                'barcode' => $product->barcode,
                                'unit' => (string) $product->unit,
                                'status' => (string) $product->status,
                                'sale_prices' => $productPrices,
                                'packages' => $productPackagesList,
                                'updated_at' => $product->updated_at === null ? null : Carbon::parse($product->updated_at)->toISOString(),
                            ];
                            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                            $itemHash = hash('sha256', $encoded);
                            $batch[] = [
                                'publication_id' => $publication->id,
                                'position' => $position++,
                                'payload' => $encoded,
                                'checksum' => $itemHash,
                            ];
                            hash_update($hash, $encoded."\n");
                            $prices += count($productPrices);
                            $packages += count($productPackagesList);
                        }
                        if ($batch !== []) {
                            DB::table('pdv_catalog_snapshot_items')->insert($batch);
                        }
                    }, 'id');

                $publication->forceFill([
                    'status' => PdvCatalogPublication::PUBLISHED,
                    'product_count' => $position,
                    'price_count' => $prices,
                    'package_count' => $packages,
                    'checksum' => hash_final($hash),
                ])->save();
                $destinatarios = DB::table('pdv_catalog_deliveries')->where('publication_id', $publication->id)->pluck('terminal_id');
                if ($destinatarios->isNotEmpty()) {
                    PdvCatalogDelivery::query()->whereIn('terminal_id', $destinatarios)
                        ->whereIn('status', [PdvCatalogDelivery::PENDING, PdvCatalogDelivery::AVAILABLE, PdvCatalogDelivery::TRANSFERRING, PdvCatalogDelivery::RECEIVED])
                        ->whereHas('publication', fn ($query) => $query->where('company_id', $publication->company_id)->where('version', '<', $publication->version))
                        ->update(['status' => PdvCatalogDelivery::CANCELLED, 'updated_at' => now()]);
                }
                PdvCatalogDelivery::query()->where('publication_id', $publication->id)
                    ->where('status', PdvCatalogDelivery::PENDING)
                    ->whereIn('terminal_id', DB::table('terminals')->where('status', 'ACTIVE')
                        ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_SECONDS))->select('id'))
                    ->update(['status' => PdvCatalogDelivery::AVAILABLE, 'updated_at' => now()]);
            });
        } catch (Throwable $exception) {
            if ($this->attempts() >= $this->tries) {
                $publication->forceFill(['status' => PdvCatalogPublication::FAILED, 'failure_code' => 'SNAPSHOT_GENERATION_FAILED'])->save();
            }
            throw $exception;
        }
    }
}
