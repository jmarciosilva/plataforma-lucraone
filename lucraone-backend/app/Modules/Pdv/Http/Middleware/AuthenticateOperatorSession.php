<?php

namespace App\Modules\Pdv\Http\Middleware;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorSessionContext;
use App\Modules\Pdv\Application\OperatorSessionLifecycle;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Terminals\Domain\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige a SEGUNDA identidade: o operador, além da máquina.
 *
 * Roda depois de `auth:sanctum` e `terminal.context`, que já provaram o
 * Terminal, e depois de `machine.ability:pdv:operator-session`, que prova o
 * alcance. Nenhuma das três substitui as outras: credencial de máquina não
 * prova que alguém está no caixa, e sessão de operador não prova que o caixa é
 * um caixa autorizado.
 *
 * A credencial da sessão vem SOMENTE do cabeçalho `X-PDV-Operator-Session`.
 * Query string é recusada explicitamente, e não apenas ignorada: URL vai para
 * log de proxy, histórico e APM, e aceitá-la "quando vier" ensinaria o cliente
 * a enviá-la ali. Corpo também não — um GET não tem corpo e a regra precisa ser
 * a mesma nas quatro rotas.
 */
class AuthenticateOperatorSession
{
    /** Nome do cabeçalho, e também o nome proibido em query string. */
    public const HEADER = 'X-PDV-Operator-Session';

    private const PARAMETRO_PROIBIDO = 'operator_session';

    public function __construct(
        private OperatorSessionLifecycle $sessoes,
        private OperatorSessionContext $contexto,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $terminal = $request->user();

        if (! $terminal instanceof Terminal) {
            return $this->recusar($request, PdvErrorResponse::CODE_FORBIDDEN, 'Esta operação exige uma credencial de Terminal.', 403);
        }

        if ($this->credencialEmLugarErrado($request)) {
            return $this->recusar($request, PdvErrorResponse::CODE_FORBIDDEN, 'A credencial da sessão operacional só é aceita em cabeçalho.', 403);
        }

        $credencial = $request->header(self::HEADER);
        $maquina = $this->credencialDeMaquina($request);

        if (! is_string($credencial) || $credencial === '' || $maquina === null) {
            return $this->recusar($request, OperatorSessionFailed::CODE_SESSION_INVALID, 'Sessão operacional ausente ou inválida.', 401);
        }

        try {
            $sessao = $this->sessoes->authenticate($terminal, $maquina, $credencial);
        } catch (OperatorSessionFailed $recusa) {
            return $this->recusar($request, $recusa->errorCode, 'Sessão operacional ausente ou inválida.', $recusa->httpStatus);
        }

        // A pessoa é carregada aqui, uma vez, e fica disponível para auditoria
        // e para o contrato — nunca lida de `$request->user()`, que é a máquina.
        $this->contexto->set($sessao, User::whereKey($sessao->user_id)->first());

        return $next($request);
    }

    /**
     * O PAT de máquina persistido desta requisição, se houver.
     *
     * Lido do sujeito da requisição e não do Terminal já estreitado, pela mesma
     * razão que o EnsureCredentialAbility e o RecoveryAdministrationContext
     * fazem igual: o `currentAccessToken()` do Sanctum devolve `null` quando a
     * requisição não trouxe token e um `TransientToken` na sessão de primeira
     * parte, enquanto o template do trait anuncia sempre um PersonalAccessToken.
     * O caminho de máquina é fail-closed: só um token persistido serve, porque é
     * o id dele que amarra a sessão do operador.
     */
    private function credencialDeMaquina(Request $request): ?PersonalAccessToken
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    private function credencialEmLugarErrado(Request $request): bool
    {
        foreach ([self::HEADER, self::PARAMETRO_PROIBIDO, 'operator-session'] as $nome) {
            if ($request->query->has($nome) || $request->request->has($nome)) {
                return true;
            }
        }

        return false;
    }

    private function recusar(Request $request, string $code, string $mensagem, int $status): Response
    {
        $this->contexto->clear();

        return PdvErrorResponse::make($request, $code, $mensagem, $status);
    }
}
