<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
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

    /**
     * A sessão sobreviveu a uma rotação legítima da credencial de máquina.
     *
     * Evento de RASTRO, não de autorização: ele registra que o vínculo técnico
     * foi religado, sem que nada de autorização tenha mudado.
     */
    public const SESSION_MACHINE_ROTATED = 'pdv.operator.session_machine_rotated';

    public const LOGOUT = 'pdv.operator.logout';

    public const BRANCH_ACCESS_GRANTED = 'pdv.operator.branch_access_granted';

    public const BRANCH_ACCESS_REVOKED = 'pdv.operator.branch_access_revoked';

    public const OPERATOR_CREATED = 'pdv.operator.created';

    public const OPERATOR_LINKED = 'pdv.operator.linked';

    public const OPERATOR_UPDATED = 'pdv.operator.updated';

    public const OPERATOR_USERNAME_CHANGED = 'pdv.operator.username_changed';

    public const OPERATOR_ACTIVATED = 'pdv.operator.activated';

    public const OPERATOR_DEACTIVATED = 'pdv.operator.deactivated';

    public const OPERATOR_PASSWORD_RESET = 'pdv.operator.password_reset';

    public const SETTINGS_UPDATED = 'pdv.operator.settings_updated';

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
     * Agora que o identificador do caixa é um USERNAME, ele é gravado em claro.
     * Na versão anterior o identificador era o e-mail e ficava apenas como
     * resumo truncado, porque e-mail é dado pessoal e um log de tentativas
     * viraria uma lista de quem tem conta no LucraOne. Um username de caixa não
     * tem esse problema: ele é operacional, aparece na tela do administrador e é
     * exatamente o que o lojista precisa ler para entender "alguém tentou entrar
     * como marcio onze vezes".
     *
     * O valor é a forma canônica e truncada. Canônica porque é por ela que o
     * login resolve, e truncada porque o campo vem do cliente e não deveria
     * conseguir inflar o registro.
     *
     * IP, request_id, Terminal e filial vêm do contexto. Senha, nunca.
     */
    public function loginRecusado(Terminal $terminal, ?User $operador, string $identificador, string $motivo): void
    {
        $this->registrar(self::LOGIN_FAILED, $terminal->tenant_id, 'Terminal', $terminal->id, $operador, [
            'terminal_id' => $terminal->id,
            'branch_id' => $terminal->branch_id,
            'attempted_username' => substr($identificador, 0, 32),
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
     * Administração do perfil de caixa.
     *
     * O ator é quem administra; `operator_id` é quem foi administrado. São
     * pessoas diferentes, e a auditoria precisa das duas — a coluna `user_id`
     * do AuditLog guarda o ATOR, como em todo o resto do projeto.
     *
     * `username` entra porque é identificador operacional, não segredo: é o que
     * o administrador vê na tela e o que o operador digita no caixa. Senha,
     * senha temporária e hash nunca entram.
     *
     * @param  array<string, mixed>  $extra
     */
    public function perfil(string $acao, PdvOperatorProfile $perfil, User $ator, array $extra = []): void
    {
        $this->registrar($acao, $perfil->tenant_id, 'PdvOperatorProfile', $perfil->id, $ator, [
            'operator_id' => $perfil->user_id,
            'username' => $perfil->username,
            'status' => $perfil->status,
        ] + $extra);
    }

    /**
     * Mudança de política operacional do estabelecimento.
     *
     * @param  array<string, mixed>  $mudancas
     */
    public function politica(string $tenantId, User $ator, array $mudancas): void
    {
        $this->registrar(self::SETTINGS_UPDATED, $tenantId, 'PdvTenantSetting', $tenantId, $ator, $mudancas);
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
