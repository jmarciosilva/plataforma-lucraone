<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Identity\Application\PasswordVerification;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Pdv\Domain\OperatorSessionCredential;
use App\Modules\Pdv\Domain\OperatorUsername;
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
 *
 * A continuidade da jornada é obtida separando dois prazos: a credencial tem
 * validade técnica finita e DESLIZANTE, e a jornada não tem teto fixo. Isso
 * substitui o prazo absoluto de oito horas da primeira versão, que encerrava o
 * turno de quem abre a loja às sete e fecha às vinte e duas.
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
        string $username,
        #[SensitiveParameter] string $senha,
    ): OperatorSessionResult {
        $identificador = OperatorUsername::canonical($username);

        $resultado = DB::transaction(function () use ($sujeito, $tokenDeMaquina, $identificador, $senha) {
            $terminal = $this->maquina->lockEligibleTerminal($sujeito->id);
            $maquina = $this->tokenVigente($terminal, $tokenDeMaquina);

            // O username é resolvido DENTRO do estabelecimento do Terminal
            // autenticado. Nunca num espaço global: é isso que permite a cada
            // cliente do LucraOne ter o seu `marcio` sem que um descubra a
            // existência do outro.
            $perfil = PdvOperatorProfile::ativoPorUsername($terminal->tenant_id, $identificador);
            $operador = $perfil === null ? null : User::whereKey($perfil->user_id)->first();

            // Senha sempre verificada, inclusive para username inexistente: é o
            // que iguala o tempo de resposta dos dois caminhos.
            if (! $this->verificacaoDeSenha->confere($operador, $senha)) {
                $this->auditoria->loginRecusado($terminal, $operador, $identificador, 'credentials');

                return OperatorSessionFailed::autenticacao();
            }

            $atribuicao = $this->autorizacao->atribuicaoAtiva($operador, $terminal);

            // Conta inativa, vínculo suspenso, operador desativado, permissão
            // ausente, filial não atribuída e filial de outro estabelecimento
            // caem todas aqui, com a MESMA resposta pública da senha errada.
            // Distinguir publicaria quem tem conta, quem continua empregado e
            // quem trabalha onde.
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

                // Mesmo operador, sessão VIGENTE e amarrada a esta máquina:
                // recuperação controlada. Exigiu nova senha (acima) e rotaciona
                // a credencial, mas NÃO mexe no teto da jornada.
                //
                // `vigente()` é conferido aqui além da reconciliação acima: é a
                // garantia de que uma sessão vencida nunca é recuperada, nem se
                // a reconciliação deixasse passar alguma linha.
                if ($ativa->vigente() && $this->amarradaA($ativa, $terminal, $maquina)) {
                    return $this->rotacionar($ativa, $operador);
                }

                // Sobrou o caso em que o turno antigo não pode ser recuperado:
                // ou a sessão já não é vigente, ou aponta para uma credencial de
                // máquina que não é esta e não foi religada por rotação legítima
                // — recuperação administrativa, substituição física ou
                // comprometimento. Fecha a antiga e abre um turno novo, com o
                // motivo real registrado.
                $this->encerrar(
                    $ativa,
                    PdvOperatorSession::STATUS_REVOKED,
                    $operador,
                    $ativa->vigente() ? 'machine-credential-replaced' : 'session-not-recoverable',
                );
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
     * Chamada em toda requisição protegida. Reconfere identidade, prazos e
     * todas as condições de autorização, sem confiar em nada guardado no login.
     *
     * Em caso de sucesso, DESLIZA o prazo técnico: é o uso legítimo que mantém
     * a credencial válida, e é por isso que uma jornada longa não precisa de
     * login novo nem de token eterno.
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
                $this->encerrar(
                    $sessao,
                    PdvOperatorSession::STATUS_EXPIRED,
                    $sessao->user,
                    $sessao->jornadaEncerrada() ? 'journey-deadline' : 'idle-deadline',
                );
            }

            throw OperatorSessionFailed::sessaoInvalida();
        }

        $operador = User::whereKey($sessao->user_id)->first();

        if ($operador === null || ! $this->autorizacao->autoriza($operador, $terminal)) {
            $this->encerrar($sessao, PdvOperatorSession::STATUS_REVOKED, $operador, 'authorization-lost');

            throw OperatorSessionFailed::sessaoInvalida();
        }

        // O deslizamento vem DEPOIS de toda a revalidação, nunca antes: uma
        // sessão que acabou de perder autorização não pode ganhar prazo.
        return $this->deslizar($sessao);
    }

    /**
     * Revalidação online explícita.
     *
     * Confirma as mesmas condições, desliza o prazo técnico e move
     * `last_revalidated_at`, que é o marco da graça offline. O teto da jornada,
     * quando existe, não se move: ele é o único prazo que nenhuma renovação
     * ultrapassa.
     */
    public function revalidate(
        Terminal $terminal,
        PersonalAccessToken $tokenDeMaquina,
        #[SensitiveParameter] string $credencial,
    ): PdvOperatorSession {
        $sessao = $this->authenticate($terminal, $tokenDeMaquina, $credencial);

        $sessao->update([
            'last_revalidated_at' => now(),
            // A revalidação é a prova de conectividade mais forte que existe no
            // contrato, então ela desliza o prazo integralmente, sem o freio de
            // gravação que vale para o uso comum.
            'expires_at' => $this->prazoDeslizante($sessao),
        ]);

        $this->auditoria->sessao(OperatorSessionAudit::SESSION_REVALIDATED, $sessao->fresh(), $sessao->user);

        return $sessao->fresh();
    }

    /**
     * Religa as sessões ativas de um Terminal à credencial de máquina promovida
     * por uma rotação legítima.
     *
     * Roda DENTRO da transação que confirma a rotação, chamada pelo listener do
     * evento de domínio — e é isso que torna a troca atômica: ou a credencial
     * nova vale e as sessões a acompanham, ou nada mudou.
     *
     * O que autoriza a religação é estreito de propósito:
     *
     *   - é o MESMO Terminal e a MESMA instalação;
     *   - a sessão apontava exatamente para o PAT que acabou de ser substituído;
     *   - a rotação é renovação, não recuperação administrativa.
     *
     * Quem decide esses três pontos é quem confirma a rotação; aqui o terceiro
     * já chegou filtrado. Nada além do vínculo técnico muda: operador,
     * autorização, prazos e auditoria permanecem, e possuir um token novo não
     * concede nada — a autorização continua sendo revalidada por requisição.
     *
     * Recuperação administrativa, substituição física e comprometimento NÃO
     * passam por aqui: eles revogam os PATs e, com eles, a amarração — a sessão
     * cai e exige reautenticação humana.
     */
    public function repointAfterRotation(
        Terminal $terminal,
        int $tokenAnterior,
        int $tokenPromovido,
        string $installationId,
    ): int {
        if ($terminal->installation_id !== $installationId || $tokenAnterior === $tokenPromovido) {
            return 0;
        }

        $sessoes = PdvOperatorSession::consultaIsolada($terminal->tenant_id)
            ->where('terminal_id', $terminal->id)
            ->where('installation_id', $installationId)
            ->where('machine_token_id', $tokenAnterior)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->get();

        foreach ($sessoes as $sessao) {
            $sessao->update([
                'machine_token_id' => $tokenPromovido,
                'machine_rotations' => $sessao->machine_rotations + 1,
            ]);

            $this->auditoria->sessao(
                OperatorSessionAudit::SESSION_MACHINE_ROTATED,
                $sessao->fresh(),
                $sessao->user,
                ['machine_rotations' => $sessao->machine_rotations + 1],
            );
        }

        return $sessoes->count();
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
     * Revoga as sessões ativas de uma pessoa no estabelecimento.
     *
     * Usada quando o perfil de caixa é desativado. Antecipação, não garantia: a
     * revalidação por requisição já recusaria, porque o perfil ativo é uma das
     * condições. Rodar aqui fecha a janela do lease offline já emitido.
     */
    public function revokeForOperator(string $tenantId, string $userId, ?User $ator, string $motivo): void
    {
        $sessoes = PdvOperatorSession::consultaIsolada($tenantId)
            ->where('user_id', $userId)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get();

        foreach ($sessoes as $sessao) {
            $this->encerrar($sessao, PdvOperatorSession::STATUS_REVOKED, $ator, $motivo);
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
     * Prazo técnico DESLIZANTE da sessão.
     *
     * `now + sliding_minutes`, limitado pelo teto da jornada quando ele existe.
     *
     * A expiração da credencial de MÁQUINA deixou de entrar nesta conta, e a
     * mudança é deliberada. Antes a sessão era limitada pelo prazo do PAT, o
     * que fazia um caixa com credencial vencendo em vinte minutos abrir um
     * turno de vinte minutos. Com a rotação religando a sessão de forma
     * transacional, a máquina deixa de ser um teto e volta a ser o que sempre
     * foi: uma prova que precisa estar VÁLIDA NO MOMENTO de cada requisição —
     * e isso o guard do Sanctum já garante, a cada requisição, para o Terminal
     * autenticado.
     */
    public function prazoDeslizante(?PdvOperatorSession $sessao = null): CarbonInterface
    {
        $deslizante = now()->addMinutes((int) config('pdv.operator_sessions.sliding_minutes'));
        $teto = $sessao?->absolute_expires_at;

        return $teto !== null && $teto->lessThan($deslizante)
            ? Carbon::instance($teto->toDateTime())
            : $deslizante;
    }

    /**
     * Teto absoluto da jornada, ou null quando não há.
     *
     * Zero em `max_minutes` significa jornada sem teto — a política do piloto, e
     * a razão de existir desta entrega: o comerciante que abre e fecha a loja no
     * mesmo turno não deve ser interrompido por um relógio.
     */
    public function tetoDaJornada(): ?CarbonInterface
    {
        $minutos = (int) config('pdv.operator_sessions.max_minutes');

        return $minutos > 0 ? now()->addMinutes($minutos) : null;
    }

    /**
     * Avança o prazo técnico, com freio de gravação.
     *
     * Sem o freio, cada requisição de um caixa movimentado viraria um UPDATE na
     * linha da sessão — e elas chegam em rajada. Só grava quando o prazo
     * avançaria mais que `slide_threshold_minutes`, o que limita a escrita a uma
     * por janela de freio sem encurtar a validade percebida.
     */
    private function deslizar(PdvOperatorSession $sessao): PdvOperatorSession
    {
        $novo = $this->prazoDeslizante($sessao);
        $freio = (int) config('pdv.operator_sessions.slide_threshold_minutes');

        if ($sessao->expires_at->greaterThan($novo->copy()->subMinutes($freio))) {
            return $sessao;
        }

        $sessao->update(['expires_at' => $novo]);

        return $sessao->fresh();
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
     * Marca como EXPIRED as sessões do Terminal que já passaram de algum prazo.
     *
     * Os DOIS prazos entram: a janela deslizante e o teto da jornada. Olhar só o
     * primeiro deixaria uma sessão cuja jornada acabou aparecer como ativa — e,
     * sendo do mesmo operador, ela seria RECUPERADA pelo login em vez de dar
     * lugar a um turno novo, o que anularia o teto na prática.
     *
     * A expiração é aplicada pela autenticação, independentemente desta
     * reconciliação; ela existe para que o caixa volte a aceitar login em vez de
     * responder conflito por causa de uma linha vencida.
     */
    private function reconciliarVencidas(Terminal $terminal): void
    {
        $consulta = PdvOperatorSession::consultaIsolada($terminal->tenant_id)
            ->where('terminal_id', $terminal->id)
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->where(fn ($query) => $query
                ->where('expires_at', '<=', now())
                ->orWhere(fn ($query) => $query
                    ->whereNotNull('absolute_expires_at')
                    ->where('absolute_expires_at', '<=', now())));

        // Lock em instrução própria, pela mesma razão explicada em
        // PdvOperatorBranchAssignment::travarPara.
        $consulta->lockForUpdate();

        $vencidas = $consulta->get();

        foreach ($vencidas as $sessao) {
            $this->encerrar(
                $sessao,
                PdvOperatorSession::STATUS_EXPIRED,
                $sessao->user,
                $sessao->jornadaEncerrada() ? 'journey-deadline' : 'idle-deadline',
            );
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
            'expires_at' => $this->prazoDeslizante(),
            // Null quando a política não tem teto de jornada, que é o padrão.
            'absolute_expires_at' => $this->tetoDaJornada(),
        ]);

        $this->auditoria->sessao(OperatorSessionAudit::LOGIN, $sessao, $operador, [
            'assignment_id' => $atribuicao->id,
        ]);
        $this->auditoria->sessao(OperatorSessionAudit::SESSION_STARTED, $sessao, $operador);

        return new OperatorSessionResult($sessao, $credencial, recuperada: false);
    }

    /**
     * Recuperação da própria sessão: credencial nova, jornada intacta.
     *
     * Serve ao caso da resposta perdida — o Java criou a sessão, não recebeu o
     * segredo, repete o login com a mesma senha. Rotacionar em vez de
     * reentregar significa que o segredo antigo, se vazou no caminho, deixa de
     * valer; e não guardar resposta em cache de idempotência significa que não
     * existe cópia do segredo em lugar nenhum.
     *
     * `absolute_expires_at` NÃO é recalculado: a jornada continua sendo a que
     * começou no primeiro login, e repetir o login não compra tempo de jornada.
     */
    private function rotacionar(PdvOperatorSession $sessao, User $operador): OperatorSessionResult
    {
        $credencial = OperatorSessionCredential::gerar();

        $sessao->update([
            'credential_hash' => OperatorSessionCredential::hash($credencial),
            'last_revalidated_at' => now(),
            'expires_at' => $this->prazoDeslizante($sessao),
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
     * credencial de máquina, e Terminal cujos vínculos de estabelecimento,
     * empresa ou filial mudaram depois do login.
     *
     * A igualdade de `machine_token_id` continua exigida, e é por isso que a
     * rotação precisa RELIGAR a sessão de forma transacional
     * (`repointAfterRotation`) em vez de a checagem ser afrouxada. Afrouxar
     * aceitaria qualquer PAT do terminal — inclusive um emitido por recuperação
     * administrativa depois de uma suspeita de comprometimento.
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
}
