<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OfflineLeaseIssuer;
use App\Modules\Pdv\Application\OperatorSessionContext;
use App\Modules\Pdv\Application\OperatorSessionLifecycle;
use App\Modules\Pdv\Http\Middleware\AuthenticateOperatorSession;
use App\Modules\Pdv\Http\Requests\OperatorSessionRequest;
use App\Modules\Pdv\Http\Responses\PdvOperatorSessionPayload;
use App\Modules\Terminals\Application\TerminalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sessão operacional do caixa.
 *
 * Quatro operações, todas sobre a sessão DESTE Terminal: não existe
 * identificador de sessão na rota, pelo mesmo motivo que não existe
 * identificador de terminal no GET de terminal — um caixa só pode agir sobre a
 * própria sessão, e isso é consequência da forma do contrato, não de uma
 * checagem que alguém pode esquecer.
 *
 * O Terminal sai do TerminalContext (middleware de máquina) e a sessão sai do
 * OperatorSessionContext (middleware de operador). Nenhum dos dois vem do
 * corpo, da query ou de cabeçalho de seleção.
 */
final class OperatorSessionController
{
    public function __construct(
        private OperatorSessionLifecycle $sessoes,
        private TerminalContext $terminalContexto,
        private OperatorSessionContext $sessaoContexto,
        private OfflineLeaseIssuer $offline,
    ) {}

    /**
     * POST /operator-sessions — autentica o operador e abre a sessão.
     *
     * 201 quando a sessão é nova, 200 quando é a recuperação da própria sessão
     * do mesmo operador — o caso da resposta perdida. A distinção é informativa
     * para o cliente; a credencial é nova nos dois.
     */
    public function store(OperatorSessionRequest $request): JsonResponse
    {
        $terminal = $this->terminalContexto->terminal();

        $resultado = $this->sessoes->start(
            $terminal,
            $this->tokenDeMaquina($terminal),
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        $operador = User::whereKey($resultado->session->user_id)->first();

        $dados = PdvOperatorSessionPayload::para(
            $resultado->session,
            $terminal,
            $operador,
            $this->offline->payload($resultado->session, $terminal, $operador, $this->tokenDeMaquina($terminal)),
        );

        return $this->sensivel(
            ['data' => PdvOperatorSessionPayload::comCredencial($dados, (string) $resultado->credencial, $resultado->session)],
            $resultado->recuperada ? 200 : 201,
        );
    }

    /**
     * GET /operator-session — estado da sessão atual.
     *
     * Consulta, não revalidação: NÃO move `last_revalidated_at` e, portanto,
     * não estende a graça offline. Um cliente que só consultasse o estado não
     * deveria ganhar tempo de operação sem rede.
     *
     * Não reentrega a credencial. Não há como: o banco só tem o hash.
     */
    public function show(): JsonResponse
    {
        $terminal = $this->terminalContexto->terminal();
        $sessao = $this->sessaoContexto->session();

        return $this->sensivel(['data' => PdvOperatorSessionPayload::para(
            $sessao,
            $terminal,
            $this->sessaoContexto->operator(),
            $this->offline->payload($sessao, $terminal, $this->sessaoContexto->operator(), $this->tokenDeMaquina($terminal)),
        )]);
    }

    /**
     * POST /operator-session/revalidate — prova de conectividade.
     *
     * Reconfere as mesmas condições da requisição protegida e move o marco da
     * graça offline, emitindo um lease novo quando o recurso está habilitado.
     * NÃO estende o prazo absoluto da sessão: continuar operando depois dele
     * exige novo login, com senha.
     */
    public function revalidate(Request $request): JsonResponse
    {
        $terminal = $this->terminalContexto->terminal();

        $sessao = $this->sessoes->revalidate(
            $terminal,
            $this->tokenDeMaquina($terminal),
            (string) $request->header(AuthenticateOperatorSession::HEADER),
        );

        return $this->sensivel(['data' => PdvOperatorSessionPayload::para(
            $sessao,
            $terminal,
            $this->sessaoContexto->operator(),
            $this->offline->payload($sessao, $terminal, $this->sessaoContexto->operator(), $this->tokenDeMaquina($terminal)),
        )]);
    }

    /**
     * DELETE /operator-session — encerra o turno.
     *
     * 204 sem corpo. Encerra a sessão humana e NADA MAIS: não despareia o
     * Terminal, não revoga a credencial de máquina e não fecha caixa
     * financeiro — que nem existe ainda. Repetir com a mesma credencial
     * legitimamente encerrada devolve 204 de novo.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->sessoes->end(
            $this->terminalContexto->terminal(),
            (string) $request->header(AuthenticateOperatorSession::HEADER),
        );

        return response()->json(null, 204)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * O PAT de máquina da requisição.
     *
     * Vem do Terminal autenticado pelo Sanctum. O middleware de máquina já
     * garantiu que existe; o fallback só existe para satisfazer o tipo.
     */
    private function tokenDeMaquina($terminal): PersonalAccessToken
    {
        $token = $terminal->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            throw new \RuntimeException('Rota de operador alcançada sem credencial de máquina.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function sensivel(?array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
