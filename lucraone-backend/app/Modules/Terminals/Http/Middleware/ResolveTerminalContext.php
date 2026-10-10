<?php

namespace App\Modules\Terminals\Http\Middleware;

use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\TerminalAuthenticationEligibility;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixa a máquina da requisição e deriva dela todo o contexto.
 *
 * Caminho exclusivo de Terminal, separado do ResolveTenantMiddleware humano.
 * O resolver humano continua recusando qualquer sujeito que não seja User — não
 * foi alterado para acomodar máquina, e é essa recusa por tipo que forma a
 * outra metade da fronteira.
 *
 * Registrado como alias 'terminal.context'. Uso:
 * `['auth:sanctum', 'terminal.context', 'machine.ability:...']`. Precisa rodar
 * DEPOIS da autenticação: sem sujeito autenticado não há máquina a resolver.
 *
 * Três responsabilidades, nesta ordem:
 *
 * 1. exigir que o sujeito seja Terminal — ability não prova tipo;
 * 2. recusar X-Tenant-ID, mesmo correto;
 * 3. derivar TerminalContext e TenantContext do Terminal autenticado.
 */
class ResolveTerminalContext
{
    public function __construct(
        private TerminalContext $terminalContext,
        private TenantContext $tenantContext,
        private TerminalAuthenticationEligibility $eligibility,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sujeito = $request->user();

        // Fronteira de sujeito. Um token humano — ainda que alguém lhe
        // atribuísse a ability de máquina — para aqui, porque a pergunta é de
        // tipo, não de alcance do token.
        if (! $sujeito instanceof Terminal) {
            return $this->recusar($request, 'Esta operação exige uma credencial de Terminal.', 403);
        }

        // X-Tenant-ID é proibido para máquina, inclusive quando aponta para o
        // tenant certo. Aceitá-lo "porque coincide" ensinaria o cliente de PDV
        // a enviar o cabeçalho, e a partir daí a divergência entre o que ele
        // manda e o vínculo real passaria a ser uma decisão de servidor. O
        // Terminal autenticado é a única autoridade sobre o contexto.
        if ($request->hasHeader('X-Tenant-ID')) {
            return $this->recusar($request, 'Credencial de Terminal não aceita seleção de estabelecimento por cabeçalho.', 403);
        }

        // Reconferido aqui, e não só no callback do Sanctum, porque este
        // middleware também é a porta de quem usa sessão de primeira parte ou
        // um sujeito injetado em teste — nenhum dos dois passa pelo callback.
        if (! $this->eligibility->allows($sujeito)) {
            return $this->recusar($request, 'Terminal não está apto a operar.', 403);
        }

        $this->terminalContext->set($sujeito);

        // O TenantScope dos models de negócio lê o TenantContext. Preencher
        // aqui é o que permite a uma rota de máquina consultar dados com o
        // mesmo filtro do caminho humano — com o tenant vindo do vínculo do
        // Terminal, nunca de cabeçalho.
        $this->tenantContext->set($sujeito->tenant_id);

        return $next($request);
    }

    /**
     * Recusa sem deixar contexto pela metade.
     *
     * Mesmo motivo do `recusar()` do TenantResolver: um contexto remanescente
     * faria a consulta seguinte rodar no estabelecimento errado.
     */
    private function recusar(Request $request, string $mensagem, int $status): Response
    {
        $this->terminalContext->clear();
        $this->tenantContext->clear();

        return PdvErrorResponse::make(
            $request,
            PdvErrorResponse::CODE_FORBIDDEN,
            $mensagem,
            $status,
        );
    }
}
