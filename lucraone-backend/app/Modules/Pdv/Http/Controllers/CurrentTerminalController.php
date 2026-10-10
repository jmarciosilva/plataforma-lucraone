<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Pdv\Http\Responses\PdvTerminalPayload;
use App\Modules\Terminals\Application\TerminalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * GET /api/v1/pdv/terminal — o próprio Terminal autenticado.
 *
 * Tudo vem do `TerminalContext`, que o middleware `terminal.context` preencheu
 * a partir do Terminal autenticado — nunca de parâmetro, corpo ou cabeçalho.
 * Por isso não há FormRequest nem identificador na rota: não existe "qual
 * terminal?" a responder. Um Terminal só pode consultar a si mesmo, e isso é
 * consequência da forma do endpoint, não de uma checagem que alguém poderia
 * esquecer.
 *
 * NÃO devolve credencial. O texto puro do token aparece uma única vez, na
 * resposta do pareamento, e não é recuperável depois.
 */
class CurrentTerminalController extends Controller
{
    public function __construct(
        private TerminalContext $contexto
    ) {}

    public function __invoke(): JsonResponse
    {
        $terminal = $this->contexto->terminal();

        $dados = PdvTerminalPayload::para(
            $terminal,
            $terminal->tenant,
            $terminal->company,
            $terminal->branch,
        );

        // Só o prazo, nunca o token nem o hash. O consumidor é concreto: o PDV
        // opera o dia inteiro e, hoje, a única recuperação possível é um novo
        // pareamento presencial. Saber a hora da expiração permite avisar o
        // operador antes do turno virar, em vez de descobrir com um 401 no meio
        // de uma venda.
        $expiraEm = $terminal->currentAccessToken()?->expires_at;

        $dados['credential'] = [
            'expires_at' => $expiraEm?->toIso8601String(),
        ];

        return response()->json(['data' => $dados]);
    }
}
