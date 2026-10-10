<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Pdv\Http\Responses\PdvTerminalPayload;
use App\Modules\Terminals\Application\MachineCredentialExpiration;
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
 * NÃO devolve credencial. Segredos são entregues somente nos fluxos de
 * pareamento e preparação de rotação/recuperação, nunca nesta consulta.
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

        // Prazo efetivo inclui o teto global do Sanctum. Permite ao PDV
        // renovar antecipadamente sem expor o token nem seu hash.
        $token = $terminal->currentAccessToken();
        $expiraEm = app(MachineCredentialExpiration::class)->effective($token);

        $dados['credential'] = [
            'expires_at' => $expiraEm?->toIso8601String(),
        ];

        return response()->json(['data' => $dados]);
    }
}
