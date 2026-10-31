<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Pdv\Application\ReceivePdvSale;
use App\Modules\Pdv\Http\Requests\SaleIngestionRequest;
use App\Modules\Terminals\Application\TerminalContext;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/pdv/sales — recebe os eventos de venda do PDV.
 *
 * Contrato combinado com o cliente Java:
 *   201 {"sale_id": "...", "received_at": "..."}  venda aceita
 *   409 {"error": "already_accepted"}             idempotency_key já aceito
 *
 * O PDV trata 2xx e 409 como sucesso (marca SENT) e qualquer outro 4xx como
 * falha permanente após as retentativas. Erros de validação saem como 422 pelo
 * renderizador de `ValidationException` das rotas `/api/v1/pdv/*`.
 *
 * Idempotência é a garantia central: o mesmo evento reenviado nunca cria dois
 * registros. A autoria (tenant/empresa/filial) vem do Terminal autenticado.
 */
final class SaleIngestionController
{
    public function __construct(
        private readonly TerminalContext $terminal,
        private readonly ReceivePdvSale $receiver,
    ) {}

    public function store(SaleIngestionRequest $request): JsonResponse
    {
        $resultado = $this->receiver->receive($this->terminal->terminal(), $request->validated());

        if ($resultado->duplicate) {
            return response()->json(['error' => 'already_accepted'], 409)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json([
            'sale_id' => $resultado->saleId,
            'received_at' => $resultado->receivedAt->toIso8601String(),
        ], 201)->header('Cache-Control', 'no-store, private');
    }
}
