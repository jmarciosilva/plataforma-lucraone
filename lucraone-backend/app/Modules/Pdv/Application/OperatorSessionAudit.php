<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Str;

/**
 * Auditoria dos fluxos de operador, com ator humano EXPLÍCITO.
 *
 * Existe por um motivo específico: nas rotas de máquina `$request->user()` é o
 * Terminal, não a pessoa. `AuditLog::logAction()` lê `auth()->user()`, então
 * usá-lo aqui gravaria o ULID do Terminal na coluna `user_id` e atribuiria a
 * ação humana à máquina — exatamente o erro que a Fase 5 precisa não cometer.
 * Todo registro daqui recebe a pessoa por parâmetro, e ela pode ser null apenas
 * quando o login falhou sem identificar conta alguma.
 *
 * O que NUNCA entra em `changes`: senha, credencial de sessão, credencial de
 * máquina, cabeçalho Authorization, hash de credencial, código de recuperação,
 * segredo de entrega e material do lease. Entram identificadores, estados e
 * correlação — o suficiente para reconstituir o que aconteceu, e nada que
 * permita repetir.
 */
class OperatorSessionAudit
{
    public const LOGIN = 'pdv.operator.login';

    public const LOGIN_FAILED = 'pdv.operator.login_failed';

    public const SESSION_STARTED = 'pdv.operator.session_started';

    public const SESSION_RECOVERED = 'pdv.operator.session_recovered';

    public const SESSION_REVALIDATED = 'pdv.operator.session_revalidated';

    public const SESSION_EXPIRED = 'pdv.operator.session_expired';

    public const SESSION_REVOKED = 'pdv.operator.session_revoked';

    public const LOGOUT = 'pdv.operator.logout';

    public const BRANCH_ACCESS_GRANTED = 'pdv.operator.branch_access_granted';

    public const BRANCH_ACCESS_REVOKED = 'pdv.operator.branch_access_revoked';

    /**
     * @param  array<string, mixed>  $extra
     */
    public function sessao(string $acao, PdvOperatorSession $sessao, ?User $operador, array $extra = []): void
    {
        $this->registrar($acao, $sessao->tenant_id, 'PdvOperatorSession', $sessao->id, $operador, [
            'terminal_id' => $sessao->terminal_id,
            'branch_id' => $sessao->branch_id,
            'operator_id' => $sessao->user_id,
            'status' => $sessao->status,
        ] + $extra);
    }

    /**
     * Tentativa de login recusada.
     *
     * O e-mail tentado não é gravado. Para investigar insistência contra uma
     * conta específica sem guardar o identificador em claro, grava-se um
     * resumo truncado dele — suficiente para agrupar tentativas, inútil para
     * listar quem tem conta. IP, request_id e Terminal vêm do contexto.
     */
    public function loginRecusado(Terminal $terminal, ?User $operador, string $identificador, string $motivo): void
    {
        $this->registrar(self::LOGIN_FAILED, $terminal->tenant_id, 'Terminal', $terminal->id, $operador, [
            'terminal_id' => $terminal->id,
            'branch_id' => $terminal->branch_id,
            'identifier_fingerprint' => substr(hash('sha256', $identificador), 0, 16),
            'reason' => $motivo,
        ]);
    }

    public function atribuicao(string $acao, PdvOperatorBranchAssignment $atribuicao, User $ator): void
    {
        $this->registrar($acao, $atribuicao->tenant_id, 'PdvOperatorBranchAssignment', $atribuicao->id, $ator, [
            'operator_id' => $atribuicao->user_id,
            'branch_id' => $atribuicao->branch_id,
            'status' => $atribuicao->status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function registrar(string $acao, string $tenantId, string $entityType, string $entityId, ?User $pessoa, array $changes): void
    {
        $request = request();

        AuditLog::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'user_id' => $pessoa?->id,
            'action' => $acao,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'request_id' => $request->header('X-Request-ID'),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
