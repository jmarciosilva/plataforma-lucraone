<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Pdv\Domain\Models\PdvCatalogDelivery;
use App\Modules\Pdv\Domain\Models\PdvCatalogPublication;
use Illuminate\Support\Facades\DB;

/** Serializa mudanças de estado por terminal usando a linha do Terminal como mutex. */
final class CatalogDeliveryLifecycle
{
    private const PART_SIZE = 200;

    public function progress(string $terminalId, string $deliveryId, int $lastPart): CatalogDeliveryTransitionResult
    {
        return DB::transaction(function () use ($terminalId, $deliveryId, $lastPart): CatalogDeliveryTransitionResult {
            $terminal = $this->lockTerminal($terminalId);
            if (! $terminal) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            $delivery = $this->lockDelivery($terminalId, $deliveryId);
            if (! $delivery || ! $this->publicationIsPublished($delivery, $terminal)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            if (in_array($delivery->status, [PdvCatalogDelivery::FAILED, PdvCatalogDelivery::CANCELLED], true)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_unavailable', 'Esta entrega não está mais disponível.', 409);
            }

            $partCount = (int) ceil($delivery->publication->product_count / self::PART_SIZE);
            if ($partCount === 0 || $lastPart >= $partCount) {
                return CatalogDeliveryTransitionResult::error('validation_error', 'Parte fora do manifesto desta carga.', 422);
            }
            if (in_array($delivery->status, [PdvCatalogDelivery::RECEIVED, PdvCatalogDelivery::APPLIED], true)) {
                return $lastPart <= (int) $delivery->last_part
                    ? CatalogDeliveryTransitionResult::success($delivery)
                    : CatalogDeliveryTransitionResult::error('catalog_progress_conflict', 'A entrega já foi recebida e não aceita novas partes.', 409);
            }
            if (! in_array($delivery->status, [PdvCatalogDelivery::AVAILABLE, PdvCatalogDelivery::TRANSFERRING], true)) {
                return CatalogDeliveryTransitionResult::error('catalog_progress_conflict', 'A transferência ainda não foi disponibilizada para este terminal.', 409);
            }
            if ($delivery->manifest_seen_at === null) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_state_conflict', 'Consulte o manifesto antes de registrar o progresso.', 409);
            }

            $current = $delivery->last_part;
            $expectedNext = $current === null ? 0 : (int) $current + 1;
            if ($current !== null && $lastPart <= (int) $current) {
                return CatalogDeliveryTransitionResult::success($delivery);
            }
            if ($lastPart !== $expectedNext) {
                return CatalogDeliveryTransitionResult::error('catalog_progress_conflict', 'A transferência precisa continuar pela próxima parte esperada.', 409);
            }

            $delivery->update(['last_part' => $lastPart, 'status' => PdvCatalogDelivery::TRANSFERRING]);

            return CatalogDeliveryTransitionResult::success($delivery->fresh());
        });
    }

    public function received(string $terminalId, string $deliveryId): CatalogDeliveryTransitionResult
    {
        return DB::transaction(function () use ($terminalId, $deliveryId): CatalogDeliveryTransitionResult {
            $terminal = $this->lockTerminal($terminalId);
            if (! $terminal) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            $delivery = $this->lockDelivery($terminalId, $deliveryId);
            if (! $delivery || ! $this->publicationIsPublished($delivery, $terminal)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            if ($delivery->status === PdvCatalogDelivery::APPLIED
                || ($delivery->status === PdvCatalogDelivery::RECEIVED && $delivery->transfer_verified_at !== null)) {
                return CatalogDeliveryTransitionResult::success($delivery);
            }
            if (in_array($delivery->status, [PdvCatalogDelivery::FAILED, PdvCatalogDelivery::CANCELLED], true)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_unavailable', 'Esta entrega não está mais disponível.', 409);
            }
            if (! in_array($delivery->status, [PdvCatalogDelivery::TRANSFERRING, PdvCatalogDelivery::RECEIVED], true)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_state_conflict', 'Consulte o manifesto antes de confirmar o recebimento.', 409);
            }
            if ($delivery->manifest_seen_at === null) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_state_conflict', 'Consulte o manifesto antes de confirmar o recebimento.', 409);
            }

            $partCount = (int) ceil($delivery->publication->product_count / self::PART_SIZE);
            if ($partCount === 0) {
                // Snapshot vazio possui zero partes; o manifesto e o checksum vazio são sua confirmação.
                if ($delivery->last_part !== null) {
                    return CatalogDeliveryTransitionResult::error('catalog_transfer_incomplete', 'A carga vazia não deve conter checkpoints de partes.', 409);
                }
            } elseif ($delivery->last_part === null || (int) $delivery->last_part !== $partCount - 1) {
                return CatalogDeliveryTransitionResult::error('catalog_transfer_incomplete', 'Ainda há partes da carga que não foram transferidas.', 409);
            }

            $delivery->update([
                'status' => PdvCatalogDelivery::RECEIVED,
                'received_at' => $delivery->received_at ?? now(),
                'transfer_verified_at' => $delivery->transfer_verified_at ?? now(),
            ]);

            return CatalogDeliveryTransitionResult::success($delivery->fresh());
        });
    }

    public function applied(string $terminalId, string $deliveryId): CatalogDeliveryTransitionResult
    {
        return DB::transaction(function () use ($terminalId, $deliveryId): CatalogDeliveryTransitionResult {
            $terminal = $this->lockTerminal($terminalId);
            if (! $terminal) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            $delivery = $this->lockDelivery($terminalId, $deliveryId);
            if (! $delivery || ! $this->publicationIsPublished($delivery, $terminal)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            if (! in_array($delivery->status, [PdvCatalogDelivery::RECEIVED, PdvCatalogDelivery::APPLIED], true)) {
                return CatalogDeliveryTransitionResult::error('catalog_apply_conflict', 'A carga precisa ser recebida antes de ser aplicada.', 409);
            }
            if ($delivery->status !== PdvCatalogDelivery::APPLIED && $delivery->transfer_verified_at === null) {
                return CatalogDeliveryTransitionResult::error('catalog_apply_conflict', 'A transferência ainda não recebeu confirmação válida.', 409);
            }

            $publication = $delivery->publication;
            $active = DB::table('pdv_terminal_catalog_states')->where('terminal_id', $terminalId)->lockForUpdate()->first();
            if ($active && (int) $active->version > (int) $publication->version) {
                return CatalogDeliveryTransitionResult::error('catalog_apply_conflict', 'Uma versão mais recente já está ativa neste terminal.', 409);
            }

            $now = now();
            $appliedAt = $delivery->applied_at ?? $now;
            if ($delivery->status !== PdvCatalogDelivery::APPLIED) {
                $delivery->update(['status' => PdvCatalogDelivery::APPLIED, 'applied_at' => $appliedAt, 'applied_version' => $publication->version]);
            }
            DB::table('pdv_terminal_catalog_states')->updateOrInsert(['terminal_id' => $terminalId], [
                'publication_id' => $publication->id,
                'version' => $publication->version,
                'applied_at' => $appliedAt,
                'created_at' => $active?->created_at ?? $now,
                'updated_at' => $now,
            ]);

            return CatalogDeliveryTransitionResult::success($delivery->fresh());
        });
    }

    public function failed(string $terminalId, string $deliveryId, string $failureCode): CatalogDeliveryTransitionResult
    {
        return DB::transaction(function () use ($terminalId, $deliveryId, $failureCode): CatalogDeliveryTransitionResult {
            $terminal = $this->lockTerminal($terminalId);
            if (! $terminal) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            $delivery = $this->lockDelivery($terminalId, $deliveryId);
            if (! $delivery || ! $this->publicationIsPublished($delivery, $terminal)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_not_found', 'Carga não encontrada para este terminal.', 404);
            }
            if ($delivery->status === PdvCatalogDelivery::FAILED) {
                return $delivery->failure_code === $failureCode
                    ? CatalogDeliveryTransitionResult::success($delivery)
                    : CatalogDeliveryTransitionResult::error('catalog_delivery_state_conflict', 'A entrega já possui outro resultado de falha.', 409);
            }
            if (in_array($delivery->status, [PdvCatalogDelivery::CANCELLED, PdvCatalogDelivery::APPLIED], true)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_state_conflict', 'Esta entrega já foi aplicada ou cancelada.', 409);
            }
            if (! in_array($delivery->status, [PdvCatalogDelivery::PENDING, PdvCatalogDelivery::AVAILABLE, PdvCatalogDelivery::TRANSFERRING, PdvCatalogDelivery::RECEIVED], true)) {
                return CatalogDeliveryTransitionResult::error('catalog_delivery_state_conflict', 'Estado de origem não permite registrar falha.', 409);
            }

            $delivery->update(['status' => PdvCatalogDelivery::FAILED, 'failure_code' => $failureCode]);

            return CatalogDeliveryTransitionResult::success($delivery->fresh());
        });
    }

    private function lockTerminal(string $terminalId): ?object
    {
        return DB::table('terminals')->where('id', $terminalId)->lockForUpdate()->first(['id', 'tenant_id', 'company_id']);
    }

    private function lockDelivery(string $terminalId, string $deliveryId): ?PdvCatalogDelivery
    {
        /** @var PdvCatalogDelivery|null $delivery */
        $delivery = PdvCatalogDelivery::query()->whereKey($deliveryId)->where('terminal_id', $terminalId)->lockForUpdate()->first();

        return $delivery;
    }

    private function publicationIsPublished(PdvCatalogDelivery $delivery, object $terminal): bool
    {
        $delivery->setRelation('publication', PdvCatalogPublication::query()->whereKey($delivery->publication_id)
            ->where('tenant_id', $terminal->tenant_id)
            ->where('company_id', $terminal->company_id)
            ->where('status', PdvCatalogPublication::PUBLISHED)->first());

        return $delivery->publication !== null;
    }
}
