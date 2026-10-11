<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Identity\Application\PasswordVerification;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Pdv\Domain\OperatorSessionCredential;
use App\Modules\Terminals\Application\MachineCredentialExpiration;
use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Domain\Models\Terminal;
use Carbon\CarbonInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use SensitiveParameter;

/**
 * Ciclo de vida da sessão operacional do caixa.
 *
 * Toda transição serializa no Terminal, pelo mesmo lock que o ciclo de
 * credencial de máquina usa (`lockEligibleTerminal`): é o Terminal que a regra
 * "uma sessão ativa por caixa" protege, e lockar a sessão não bastaria — duas
 * requisições simultâneas num caixa livre não encontram linha alguma para
 * lockar e criariam duas sessões. Dentro do lock, o estado é relido do banco;
 * nada decidido antes dele é considerado.
 *
 * Não há chamada externa dentro das transações, e nenhuma delas deixa estado
 * parcial: a credencial só é entregue depois do commit da linha que a
 * representa.
 */
class OperatorSessionLifecycle
{
    public function __construct(
        private TerminalCredentialLifecycle $maquina,
        private MachineCredentialExpiration $expiracao,
        private OperatorAuthorization $autorizacao,
        private PasswordVerification $verificacaoDeSenha,
        private OperatorSessionAudit $auditoria,
    ) {}

    /**
     * Autentica o operador e abre (ou recupera) a sessão do Terminal.
     *
     * @throws OperatorSessionFailed
     * @throws AuthenticationException quando o problema é da máquina
     */
    public function start(
        Terminal $sujeito,
        PersonalAccessToken $tokenDeMaquina,
        string $email,
        #[SensitiveParameter] string $senha,
    ): OperatorSessionResult {
        $identificador = $this->normalizar($email);

        $resultado = DB::transaction(function () use ($sujeito, $tokenDeMaquina, $identificador, $senha) {
            $terminal = $this->maquina->lockEligibleTerminal($sujeito->id);
            $maquina = $this->tokenVigente($terminal, $tokenDeMaquina);

            // Uma pessoa não é entidade de estabelecimento: a busca é global
            // por e-mail, e a autorização é que amarra ao estabelecimento do
            // Terminal. Sem global scopes porque User não tem TenantScope.
            $operador = User::where('email', $identificador)->first();

            // Senha sempre verificada, inclusive para e-mail sem conta: é o
            // que iguala o tempo de resposta dos dois caminhos.
            if (! $this->verificacaoDeSenha->confere($operador, $senha)) {
                $this->auditoria->loginRecusado($terminal, $operador, $identificador, 'credentials');

                return OperatorSessionFailed::autenticacao();
            }

            $atribuicao = $this->autorizacao->atribuicaoAtiva($operador, $terminal);

            // Conta inativa, vínculo suspenso, permissão ausente, filial não
            // atribuída e filial de outro estabelecimento caem todas aqui, com
            // a MESMA resposta pública da senha errada. Distinguir publicaria
            // quem tem conta, quem continua empregado e quem trabalha onde.
            if ($atribuicao === null) {
                $this->auditoria->loginRecusado($terminal, $operador, $identificador, 'authorization');

                return OperatorSessionFailed::autenticacao();
            }

            $this->reconciliarVencidas($terminal);

            $ativa = $this->sessaoAtivaDoTerminal($terminal, bloquear: true);

            if ($ativa !== null) {
                // Outro operador está com o caixa. Trocar de operador não é
                // decisão de quem chega: encerrar o turno alheio exige logout
                // ou revogação administrativa.
                if ($ativa->user_id !== $operador->id) {
                    return OperatorSessionFailed::conflito();
                }

                // Mesmo operador e a sessão continua amarrada a esta máquina:
                // recuperação controlada. Exigiu nova senha (acima) e rotaciona
                // a credencial, mas NÃO mexe no prazo absoluto.
                if ($this->amarradaA($ativa, $terminal, $maquina)) {
                    return $this->rotacionar($ativa, $operador);
                }

                // Mesmo operador, mas a sessão pertence a uma credencial de
                // máquina que já não é esta: a rotação da máquina derruba o
                // turno. Fecha a antiga antes de abrir a nova, para o caixa
                // nunca ter duas.
                $this->encerrar($ativa, PdvOperatorSession::STATUS_REVOKED, $operador, 'machine-credential-rotated');
            }

            return $this->abrir($terminal, $maquina, $operador, $atribuicao);
        }, 3);

        // A recusa é commitada junto da auditoria da tentativa e só então
        // vira exceção: um rollback apagaria o rastro de quem tentou.
        if ($resultado instanceof OperatorSessionFailed) {
            throw $resultado;
        }

        return $resultado;
    }

    /**
     * A sessão apresentada vale AGORA, neste Terminal?
     *
     * Chamada em toda requisição protegida. Reconfere identidade, prazo e as
     * cinco condições de autorização, sem confiar em nada guardado no login.
     *
     * @throws OperatorSessionFailed
     */
    public function authenticate(
        Terminal $terminal,
        PersonalAccessToken $tokenDeMaquina,
        #[SensitiveParameter] string $credencial,
    ): PdvOperatorSession {
        $sessao = $this->localizar($credencial);

        // Identidade completa, não só o Terminal: a mesma credencial não vale
        // em outra instalação, nem sob outra credencial de máquina, nem se os
        // vínculos do Terminal tiverem mudado desde o login.
        if ($sessao === null || ! $this->amarradaA($sessao, $terminal, $tokenDeMaquina)) {
            throw OperatorSessionFailed::sessaoInvalida();
        }

        if (! $sessao->vigente()) {
            // Reconcilia uma única vez: a partir daqui o status já não é
            // ACTIVE e repetições não escrevem mais nada.
            if ($sessao->isActive()) {
                $this->encerrar($sessao, PdvOperatorSession::STATUS_EXPIRED, $sessao->user, 'absolute-deadline');
            }

            throw OperatorSessionFailed::sessaoInvalida();
        }

        $operador = User::whereKey($sessao->user_id)->first();

        if ($operador === null || ! $this->autorizacao->autoriza($operador, $terminal)) {
            $this->encerrar($sessao, PdvOperatorSession::STATUS_REVOKED, $operador, 'authorization-lost');

            throw OperatorSessionFailed::sessaoInvalida();
        }

        return $sessao;
    }

    /**
     * Revalidação online explícita.
     *
     * Confirma as mesmas condições e move `last_revalidated_at`, que é o marco
     * da graça offline. NÃO estende `expires_at`: prorrogar a sessão sem novo
     * login é exatamente o que a política proíbe.
     */
    public function revalidate(
        Terminal $terminal,
        PersonalAccessToken $tokenDeMaquina,
        #[SensitiveParameter] string $credencial,
    ): PdvOperatorSession {
        $sessao = $this->authenticate($terminal, $tokenDeMaquina, $credencial);

        $sessao->update(['last_revalidated_at' => now()]);

        $this->auditoria->sessao(OperatorSessionAudit::SESSION_REVALIDATED, $sessao, $sessao->user);

        return $sessao->fresh();
    }

    /**
     * Logout. Idempotente para a mesma credencial legitimamente encerrada.
     *
     * Credencial desconhecida ou de outro Terminal continua sendo 401 — a
     * idempotência não é um passe para sondar credenciais.
     *
     * Diferente das rotas de operação, NÃO exige que a credencial de máquina
     * seja a mesma do login: um cliente que acabou de rotacionar a máquina
     * ainda deve conseguir fechar o turno anterior, e fechar sessão é sempre a
     * direção segura.
     *
     * @throws OperatorSessionFailed
     */
    public function end(Terminal $terminal, #[SensitiveParameter] string $credencial): void
    {
        $sessao = $this->localizar($credencial);

        if ($sessao === null
            || $sessao->terminal_id !== $terminal->id
            || $sessao->tenant_id !== $terminal->tenant_id
            || $sessao->installation_id !== $terminal->installation_id) {
            throw OperatorSessionFailed::sessaoInvalida();
        }

        if (! $sessao->isActive()) {
            return;
        }

        DB::transaction(function () use ($sessao) {
            $atual = PdvOperatorSession::withoutGlobalScopes()->whereKey($sessao->id)->lockForUpdate()->first();

            if ($atual === null || ! $atual->isActive()) {
                return;
            }

            $atual->update(['status' => PdvOperatorSession::STATUS_ENDED, 'ended_at' => now()]);

            $this->auditoria->sessao(OperatorSessionAudit::LOGOUT, $atual->fresh(), $atual->user);
        }, 3);
    }

    /**
     * Revoga as sessões ativas atingidas pela perda de uma atribuição.
     *
     * Antecipação, não garantia: a revalidação por requisição já recusaria essas
     * sessões no próximo acesso. Rodar aqui fecha a janela em que um caixa
     * continuaria operando offline com um lease emitido antes da revogação.
     */
    public function revokeForAssignment(PdvOperatorBranchAssignment $atribuicao, ?User $ator): void
    {
        $sessoes = PdvOperatorSession::consultaIsolada($atribuicao->tenant_id)
            ->where('user_id', $atribuicao->user_id)
            ->where('branch_id', $atribuicao->branch_id)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get();

        foreach ($sessoes as $sessao) {
            $this->encerrar($sessao, PdvOperatorSession::STATUS_REVOKED, $ator, 'branch-access-revoked');
        }
    }

    /**
     * Revoga as sessões ativas de um Terminal que saiu de operação.
     *
     * Pendurado no mesmo gancho que apaga as credenciais de máquina: sair de
     * ACTIVE encerra o turno em andamento.
     */
    public function revokeForTerminal(Terminal $terminal, string $motivo = 'terminal-not-operational'): void
    {
        $sessoes = PdvOperatorSession::withoutGlobalScopes()
            ->where('terminal_id', $terminal->id)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->get();

        foreach ($sessoes as $sessao) {
            $this->encerrar($sessao, PdvOperatorSession::STATUS_REVOKED, null, $motivo);
        }
    }

    /**
     * Prazo efetivo de uma sessão: o menor entre o teto de política e a
     * expiração efetiva da credencial de máquina que a abriu.
     *
     * Uma sessão nunca sobrevive à máquina. Sem este mínimo, um caixa cuja
     * credencial vence em 20 minutos abriria um turno de 8 horas e o Java
     * descobriria a incoerência só na primeira recusa.
     */
    public function prazo(PersonalAccessToken $tokenDeMaquina): CarbonInterface
    {
        $politica = now()->addMinutes((int) config('pdv.operator_sessions.ttl_minutes'));
        $maquina = $this->expiracao->effective($tokenDeMaquina);

        return $maquina !== null && $maquina->lessThan($politica)
            ? Carbon::instance($maquina->toDateTime())
            : $politica;
    }

    public function sessaoAtivaDoTerminal(Terminal $terminal, bool $bloquear = false): ?PdvOperatorSession
    {
        $consulta = PdvOperatorSession::consultaIsolada($terminal->tenant_id)
            ->where('terminal_id', $terminal->id)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE);

        // Lock em instrução própria, pela mesma razão explicada em
        // PdvOperatorBranchAssignment::travarPara.
        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->first();
    }

    /**
     * Marca como EXPIRED as sessões do Terminal que já passaram do prazo.
     *
     * A expiração é aplicada pela autenticação, independentemente desta
     * reconciliação; ela existe para que o caixa livre volte a aceitar login em
     * vez de responder conflito por causa de uma linha vencida.
     */
    private function reconciliarVencidas(Terminal $terminal): void
    {
        $vencidas = PdvOperatorSession::consultaIsolada($terminal->tenant_id)
            ->where('terminal_id', $terminal->id)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        foreach ($vencidas as $sessao) {
            $this->encerrar($sessao, PdvOperatorSession::STATUS_EXPIRED, $sessao->user, 'absolute-deadline');
        }
    }

    private function abrir(
        Terminal $terminal,
        PersonalAccessToken $maquina,
        User $operador,
        PdvOperatorBranchAssignment $atribuicao,
    ): OperatorSessionResult {
        $credencial = OperatorSessionCredential::gerar();
        $agora = now();

        $sessao = PdvOperatorSession::create([
            'tenant_id' => $terminal->tenant_id,
            'company_id' => $terminal->company_id,
            'branch_id' => $terminal->branch_id,
            'terminal_id' => $terminal->id,
            'installation_id' => $terminal->installation_id,
            'user_id' => $operador->id,
            'machine_token_id' => $maquina->id,
            'credential_hash' => OperatorSessionCredential::hash($credencial),
            'status' => PdvOperatorSession::STATUS_ACTIVE,
            'authenticated_at' => $agora,
            'last_revalidated_at' => $agora,
            'expires_at' => $this->prazo($maquina),
        ]);

        $this->auditoria->sessao(OperatorSessionAudit::LOGIN, $sessao, $operador, [
            'assignment_id' => $atribuicao->id,
        ]);
        $this->auditoria->sessao(OperatorSessionAudit::SESSION_STARTED, $sessao, $operador);

        return new OperatorSessionResult($sessao, $credencial, recuperada: false);
    }

    /**
     * Recuperação da própria sessão: credencial nova, prazo intacto.
     *
     * Serve ao caso da resposta perdida — o Java criou a sessão, não recebeu o
     * segredo, repete o login com a mesma senha. Rotacionar em vez de
     * reentregar significa que o segredo antigo, se vazou no caminho, deixa de
     * valer; e não guardar resposta em cache de idempotência significa que não
     * existe cópia do segredo em lugar nenhum.
     */
    private function rotacionar(PdvOperatorSession $sessao, User $operador): OperatorSessionResult
    {
        $credencial = OperatorSessionCredential::gerar();

        $sessao->update([
            'credential_hash' => OperatorSessionCredential::hash($credencial),
            'last_revalidated_at' => now(),
        ]);

        $atualizada = $sessao->fresh();

        $this->auditoria->sessao(OperatorSessionAudit::SESSION_RECOVERED, $atualizada, $operador);

        return new OperatorSessionResult($atualizada, $credencial, recuperada: true);
    }

    private function encerrar(PdvOperatorSession $sessao, string $status, ?User $ator, string $motivo): void
    {
        $sessao->update([
            'status' => $status,
            'ended_at' => $status === PdvOperatorSession::STATUS_ENDED ? now() : $sessao->ended_at,
            'revoked_at' => $status === PdvOperatorSession::STATUS_REVOKED ? now() : $sessao->revoked_at,
        ]);

        $acao = $status === PdvOperatorSession::STATUS_EXPIRED
            ? OperatorSessionAudit::SESSION_EXPIRED
            : OperatorSessionAudit::SESSION_REVOKED;

        $this->auditoria->sessao($acao, $sessao->fresh(), $ator, ['reason' => $motivo]);
    }

    /**
     * A sessão pertence a esta máquina, nesta instalação, com estes vínculos?
     *
     * Os seis campos são conferidos juntos porque cada um fecha um reuso
     * diferente: outro Terminal, outra instalação do mesmo Terminal, outra
     * credencial de máquina (rotação), e Terminal cujos vínculos de
     * estabelecimento, empresa ou filial mudaram depois do login.
     */
    private function amarradaA(PdvOperatorSession $sessao, Terminal $terminal, PersonalAccessToken $maquina): bool
    {
        return $sessao->terminal_id === $terminal->id
            && $sessao->tenant_id === $terminal->tenant_id
            && $sessao->company_id === $terminal->company_id
            && $sessao->branch_id === $terminal->branch_id
            && $sessao->installation_id === $terminal->installation_id
            && (int) $sessao->machine_token_id === (int) $maquina->id;
    }

    /**
     * Busca pela credencial.
     *
     * Sem global scopes: a localização precisa acontecer antes de qualquer
     * conclusão sobre estabelecimento, e o isolamento é feito logo depois,
     * conferindo a amarração contra o Terminal autenticado. Valor malformado
     * nem chega ao banco e recebe a mesma resposta de valor inexistente.
     */
    private function localizar(#[SensitiveParameter] string $credencial): ?PdvOperatorSession
    {
        if (! OperatorSessionCredential::bemFormada($credencial)) {
            return null;
        }

        return PdvOperatorSession::withoutGlobalScopes()
            ->where('credential_hash', OperatorSessionCredential::hash($credencial))
            ->first();
    }

    /**
     * Relê o PAT de máquina sob lock e confirma que ele ainda vale.
     *
     * O middleware já autenticou, mas entre a autenticação e esta transação a
     * credencial pode ter sido rotacionada ou revogada. Problema de máquina sai
     * como 401 genérico, igual a qualquer outra recusa de credencial.
     */
    private function tokenVigente(Terminal $terminal, PersonalAccessToken $token): PersonalAccessToken
    {
        /** @var PersonalAccessToken|null $atual */
        $atual = $terminal->tokens()->whereKey($token->id)->lockForUpdate()->first();

        if ($atual === null || ! $this->expiracao->valid($atual)) {
            throw new AuthenticationException;
        }

        return $atual;
    }

    private function normalizar(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
