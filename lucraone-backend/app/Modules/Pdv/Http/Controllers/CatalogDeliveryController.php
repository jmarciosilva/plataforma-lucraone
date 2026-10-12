<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Pdv\Domain\Models\PdvCatalogDelivery;
use App\Modules\Pdv\Domain\Models\PdvCatalogPublication;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Terminals\Application\TerminalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class CatalogDeliveryController
{
    private const PART_SIZE = 200;

    public function __construct(private readonly TerminalContext $terminal) {}

    public function pending(): JsonResponse
    {
        PdvCatalogDelivery::query()->where('terminal_id', $this->terminal->terminalId())
            ->where('status', PdvCatalogDelivery::PENDING)
            ->whereHas('publication', fn ($query) => $query->where('status', PdvCatalogPublication::PUBLISHED))
            ->update(['status' => PdvCatalogDelivery::AVAILABLE, 'updated_at' => now()]);

        $deliveries = PdvCatalogDelivery::query()->where('terminal_id', $this->terminal->terminalId())
            ->whereIn('status', [PdvCatalogDelivery::PENDING, PdvCatalogDelivery::AVAILABLE, PdvCatalogDelivery::TRANSFERRING])
            ->whereHas('publication', fn ($query) => $query->where('status', PdvCatalogPublication::PUBLISHED))
            ->with('publication:id,version,schema_version,checksum,product_count,price_count,package_count,created_at')
            ->orderBy('created_at')->limit(20)->get();

        return response()->json(['data' => $deliveries->map(fn (PdvCatalogDelivery $delivery) => [
            'delivery_id' => $delivery->id,
            'publication_id' => $delivery->publication->id,
            'version' => $delivery->publication->version,
            'status' => $delivery->status,
            'manifest_url' => route('pdv.catalog-deliveries.manifest', $delivery, false),
        ])])->header('Cache-Control', 'private, no-store');
    }

    public function manifest(Request $request, string $deliveryId): JsonResponse
    {
        $delivery = $this->delivery($deliveryId);
        if (! $delivery || $delivery->publication?->status !== PdvCatalogPublication::PUBLISHED) {
            return $this->error($request, 'catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
        }
        if (in_array($delivery->status, [PdvCatalogDelivery::FAILED, PdvCatalogDelivery::CANCELLED], true)) {
            return $this->error($request, 'catalog_delivery_unavailable', 'Esta entrega não está mais disponível.', 409);
        }
        if ($delivery->status === PdvCatalogDelivery::AVAILABLE) {
            $delivery->update(['status' => PdvCatalogDelivery::TRANSFERRING]);
        }
        $publication = $delivery->publication;

        return response()->json(['data' => [
            'delivery_id' => $delivery->id,
            'publication_id' => $publication->id,
            'version' => (int) $publication->version,
            'schema_version' => (int) $publication->schema_version,
            'checksum_algorithm' => 'sha256',
            'checksum' => $publication->checksum,
            'product_count' => (int) $publication->product_count,
            'price_count' => (int) $publication->price_count,
            'package_count' => (int) $publication->package_count,
            'part_size' => self::PART_SIZE,
            'part_count' => (int) ceil($publication->product_count / self::PART_SIZE),
            'resume_from_part' => $delivery->last_part === null ? 0 : $delivery->last_part + 1,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function part(Request $request, string $deliveryId, int $part): Response
    {
        $delivery = $this->delivery($deliveryId);
        if (! $delivery || $delivery->publication?->status !== PdvCatalogPublication::PUBLISHED) {
            return $this->error($request, 'catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
        }
        if (in_array($delivery->status, [PdvCatalogDelivery::FAILED, PdvCatalogDelivery::CANCELLED], true)) {
            return $this->error($request, 'catalog_delivery_unavailable', 'Esta entrega não está mais disponível.', 409);
        }
        $count = (int) $delivery->publication->product_count;
        $partCount = (int) ceil($count / self::PART_SIZE);
        if ($part < 0 || $part >= max(1, $partCount)) {
            return $this->error($request, PdvErrorResponse::CODE_VALIDATION, 'Parte de catálogo inválida.', 422);
        }
        $items = DB::table('pdv_catalog_snapshot_items')->where('publication_id', $delivery->publication_id)
            ->whereBetween('position', [$part * self::PART_SIZE, (($part + 1) * self::PART_SIZE) - 1])
            ->orderBy('position')->get(['position', 'payload', 'checksum']);
        $raw = $items->pluck('payload')->implode("\n").($items->isNotEmpty() ? "\n" : '');

        return response($raw, 200, [
            'Content-Type' => 'application/x-ndjson; charset=utf-8',
            'X-PDV-Catalog-Part' => (string) $part,
            'X-PDV-Catalog-Part-Count' => (string) $partCount,
            'X-PDV-Catalog-Part-Checksum' => hash('sha256', $raw),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function progress(Request $request, string $deliveryId): JsonResponse
    {
        $validator = Validator::make($request->all(), ['last_part' => ['required', 'integer', 'min:0']]);
        if ($validator->fails()) {
            return $this->error($request, PdvErrorResponse::CODE_VALIDATION, 'Progresso de transferência inválido.', 422, $validator->errors()->messages());
        }
        $validated = $validator->validated();
        $delivery = $this->delivery($deliveryId);
        if (! $delivery) {
            return $this->error($request, 'catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
        }
        if (in_array($delivery->status, [PdvCatalogDelivery::FAILED, PdvCatalogDelivery::CANCELLED], true)) {
            return $this->error($request, 'catalog_delivery_unavailable', 'Esta entrega não está mais disponível.', 409);
        }
        $lastPart = (int) $validated['last_part'];
        $lastAvailablePart = (int) ceil($delivery->publication->product_count / self::PART_SIZE) - 1;
        if ($lastPart > $lastAvailablePart) {
            return $this->error($request, PdvErrorResponse::CODE_VALIDATION, 'Parte fora do manifesto desta carga.', 422);
        }
        $expectedNext = $delivery->last_part === null ? 0 : (int) $delivery->last_part + 1;
        if ($lastPart > (int) $delivery->last_part && $lastPart !== $expectedNext) {
            return $this->error($request, 'catalog_progress_conflict', 'A transferência precisa continuar pela próxima parte esperada.', 409);
        }
        if (! in_array($delivery->status, [PdvCatalogDelivery::RECEIVED, PdvCatalogDelivery::APPLIED], true)) {
            $delivery->update(['last_part' => max((int) $delivery->last_part, $lastPart), 'status' => PdvCatalogDelivery::TRANSFERRING]);
        }

        return response()->json(['data' => ['status' => $delivery->status, 'last_part' => $delivery->last_part]]);
    }

    public function received(Request $request, string $deliveryId): JsonResponse
    {
        $delivery = $this->delivery($deliveryId);
        if (! $delivery || $delivery->publication?->status !== PdvCatalogPublication::PUBLISHED) {
            return $this->error($request, 'catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
        }
        if (in_array($delivery->status, [PdvCatalogDelivery::FAILED, PdvCatalogDelivery::CANCELLED], true)) {
            return $this->error($request, 'catalog_delivery_unavailable', 'Esta entrega não está mais disponível.', 409);
        }
        $lastPart = (int) ceil($delivery->publication->product_count / self::PART_SIZE) - 1;
        if ($lastPart >= 0 && (int) $delivery->last_part < $lastPart) {
            return $this->error($request, 'catalog_transfer_incomplete', 'Ainda há partes da carga que não foram transferidas.', 409);
        }
        if ($delivery->status !== PdvCatalogDelivery::APPLIED) {
            $delivery->update(['status' => PdvCatalogDelivery::RECEIVED, 'received_at' => $delivery->received_at ?? now()]);
        }

        return response()->json(['data' => ['status' => $delivery->status]]);
    }

    public function applied(Request $request, string $deliveryId): JsonResponse
    {
        $terminalId = $this->terminal->terminalId();
        $result = DB::transaction(function () use ($deliveryId, $terminalId): ?PdvCatalogDelivery {
            /** @var PdvCatalogDelivery|null $delivery */
            $delivery = PdvCatalogDelivery::query()->whereKey($deliveryId)->where('terminal_id', $terminalId)->lockForUpdate()->first();
            if (! $delivery || ! in_array($delivery->status, [PdvCatalogDelivery::RECEIVED, PdvCatalogDelivery::APPLIED], true)) {
                return null;
            }
            /** @var PdvCatalogPublication|null $publication */
            $publication = PdvCatalogPublication::query()->whereKey($delivery->publication_id)->where('status', PdvCatalogPublication::PUBLISHED)->first();
            if (! $publication) {
                return null;
            }
            $active = DB::table('pdv_terminal_catalog_states')->where('terminal_id', $terminalId)->lockForUpdate()->first();
            if ($active && (int) $active->version > (int) $publication->version) {
                return null;
            }
            $now = now();
            $delivery->update(['status' => PdvCatalogDelivery::APPLIED, 'applied_at' => $delivery->applied_at ?? $now, 'applied_version' => $publication->version]);
            DB::table('pdv_terminal_catalog_states')->updateOrInsert(['terminal_id' => $terminalId], [
                'publication_id' => $publication->id,
                'version' => $publication->version,
                'applied_at' => $delivery->applied_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /** @var PdvCatalogDelivery $refreshed */
            $refreshed = $delivery->fresh();

            return $refreshed;
        });
        if (! $result) {
            return $this->error($request, 'catalog_apply_conflict', 'A carga não pode ser marcada como aplicada neste estado ou substituir versão mais recente.', 409);
        }

        return response()->json(['data' => ['status' => $result->status, 'version' => $result->applied_version]]);
    }

    public function failed(Request $request, string $deliveryId): JsonResponse
    {
        $validator = Validator::make($request->all(), ['code' => ['required', 'string', 'in:CHECKSUM_MISMATCH,UNSUPPORTED_SCHEMA,STORAGE_ERROR,APPLY_FAILED']]);
        if ($validator->fails()) {
            return $this->error($request, PdvErrorResponse::CODE_VALIDATION, 'Código de falha inválido.', 422, $validator->errors()->messages());
        }
        $validated = $validator->validated();
        $delivery = $this->delivery($deliveryId);
        if (! $delivery) {
            return $this->error($request, 'catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
        }
        if ($delivery->publication?->status !== PdvCatalogPublication::PUBLISHED
            || in_array($delivery->status, [PdvCatalogDelivery::CANCELLED, PdvCatalogDelivery::APPLIED], true)) {
            return $this->error($request, 'catalog_delivery_unavailable', 'Esta entrega não pode receber uma falha neste estado.', 409);
        }
        if ($delivery->status !== PdvCatalogDelivery::APPLIED) {
            $delivery->update(['status' => PdvCatalogDelivery::FAILED, 'failure_code' => $validated['code']]);
        }

        return response()->json(['data' => ['status' => $delivery->status]]);
    }

    private function delivery(string $id): ?PdvCatalogDelivery
    {
        if (! Str::isUlid($id)) {
            return null;
        }

        return PdvCatalogDelivery::query()->with('publication')->whereKey($id)
            ->where('terminal_id', $this->terminal->terminalId())->first();
    }

    /** @param array<string, list<string>> $errors */
    private function error(Request $request, string $code, string $message, int $status, array $errors = []): JsonResponse
    {
        return PdvErrorResponse::make($request, $code, $message, $status, $errors);
    }
}
