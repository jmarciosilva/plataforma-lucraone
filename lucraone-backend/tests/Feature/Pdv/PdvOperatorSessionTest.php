<?php

namespace Tests\Feature\Pdv;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorSessionAudit;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalCredentialOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

/**
 * Fase 5 — ciclo de vida da sessão operacional.
 *
 * Duas propriedades são verificadas em quase todos os testes daqui:
 *
 *   1. a sessão vale somente nas DUAS identidades com que nasceu — este
 *      terminal, esta instalação, esta credencial de máquina;
 *   2. a autorização é reavaliada A CADA REQUISIÇÃO, e não herdada do login.
 *      Desativar a conta, suspender o vínculo, revogar a filial ou retirar a
 *      permissão derruba a sessão na requisição seguinte, sem nenhum evento.
 */
#[Group('pdv-operator')]
class PdvOperatorSessionTest extends PdvOperatorTestCase
{
    public function test_consulta_devolve_a_sessao_e_nunca_a_credencial(): void
    {
        $credencial = $this->abrirSessao();

        $resposta = $this->consultarSessao($credencial);

        $resposta->assertOk()
            ->assertJsonPath('data.session.status', PdvOperatorSession::STATUS_ACTIVE)
            ->assertJsonPath('data.operator.id', $this->operador->id)
            ->assertJsonMissingPath('data.credential');

        $this->assertStringNotContainsString($credencial, $resposta->getContent());
        $this->assertStringNotContainsString(hash('sha256', $credencial), $resposta->getContent());
        $this->assertSame('no-store, private', $resposta->headers->get('Cache-Control'));
    }

    /**
     * Consultar não é revalidar: o GET não move o marco da graça offline.
     * Caso contrário um cliente ganharia tempo de operação sem rede apenas
     * olhando o próprio estado.
     */
    public function test_consulta_nao_move_o_marco_de_revalidacao(): void
    {
        $credencial = $this->abrirSessao();
        $antes = PdvOperatorSession::withoutGlobalScopes()->sole()->last_revalidated_at;

        $this->travel(5)->minutes();
        $this->consultarSessao($credencial)->assertOk();

        $this->assertTrue($antes->equalTo(PdvOperatorSession::withoutGlobalScopes()->sole()->last_revalidated_at));
    }

    /**
     * Revalidar move o marco offline E desliza o prazo técnico.
     *
     * Na primeira versão o prazo era absoluto e revalidar não o movia; é
     * justamente essa regra que impedia a jornada longa. O que continua
     * intocável é o teto da JORNADA — quando configurado.
     */
    public function test_revalidar_move_o_marco_e_desliza_o_prazo_tecnico(): void
    {
        $credencial = $this->abrirSessao();
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $prazo = $sessao->expires_at;
        $marco = $sessao->last_revalidated_at;

        $this->travel(10)->minutes();
        $this->revalidar($credencial)->assertOk();

        $atualizada = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertTrue($atualizada->last_revalidated_at->greaterThan($marco));
        $this->assertTrue($atualizada->expires_at->greaterThan($prazo));
        $this->assertNull($atualizada->absolute_expires_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::SESSION_REVALIDATED,
            'user_id' => $this->operador->id,
        ]);
    }

    /**
     * Com teto de jornada configurado, nenhuma revalidação passa dele.
     */
    public function test_revalidacao_nunca_passa_do_teto_da_jornada(): void
    {
        config(['pdv.operator_sessions.max_minutes' => 60]);

        $credencial = $this->abrirSessao();
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertNotNull($sessao->absolute_expires_at);

        $this->travel(30)->minutes();
        $this->revalidar($credencial)->assertOk();

        $atualizada = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertTrue($atualizada->expires_at->lessThanOrEqualTo($sessao->absolute_expires_at));

        // Passado o teto, nem o uso legítimo salva a sessão.
        $this->travel(31)->minutes();
        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
        $this->assertSame(PdvOperatorSession::STATUS_EXPIRED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
    }

    public function test_logout_encerra_a_sessao_e_e_idempotente(): void
    {
        $credencial = $this->abrirSessao();

        $this->logout($credencial)->assertNoContent();

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $this->assertSame(PdvOperatorSession::STATUS_ENDED, $sessao->status);
        $this->assertNotNull($sessao->ended_at);

        // Mesma credencial legitimamente encerrada: 204 de novo, sem registrar
        // um segundo logout.
        $this->logout($credencial)->assertNoContent();
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', OperatorSessionAudit::LOGOUT)->count());
    }

    public function test_logout_nao_despareia_o_terminal_nem_revoga_a_credencial_de_maquina(): void
    {
        $credencial = $this->abrirSessao();

        $this->logout($credencial)->assertNoContent();

        $terminal = $this->terminal->fresh();
        $this->assertSame(Terminal::STATUS_ACTIVE, $terminal->status);
        $this->assertNotNull($terminal->installation_id);
        $this->assertSame(1, $terminal->tokens()->count());

        // A máquina continua operando e pode receber outro operador.
        $this->login()->assertCreated();
    }

    public function test_logout_recusa_credencial_desconhecida(): void
    {
        $this->abrirSessao();

        $this->assertErroPdv(
            $this->logout(str_repeat('a', 64)),
            401,
            OperatorSessionFailed::CODE_SESSION_INVALID,
        );

        $this->assertSame(PdvOperatorSession::STATUS_ACTIVE, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
    }

    public function test_logout_recusa_credencial_de_outro_terminal(): void
    {
        $credencial = $this->abrirSessao();

        $outro = $this->terminalOperacional($this->branch);
        $maquinaDoOutro = $this->credencialDeMaquina($outro);

        $this->assertErroPdv(
            $this->logout($credencial, $maquinaDoOutro),
            401,
            OperatorSessionFailed::CODE_SESSION_INVALID,
        );

        $this->assertSame(PdvOperatorSession::STATUS_ACTIVE, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
    }

    public function test_sessao_expirada_e_recusada_e_reconciliada_uma_vez(): void
    {
        $credencial = $this->abrirSessao();

        $this->travel(481)->minutes();

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $this->assertSame(PdvOperatorSession::STATUS_EXPIRED, $sessao->status);

        // A reconciliação é única: a segunda tentativa não registra de novo.
        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', OperatorSessionAudit::SESSION_EXPIRED)->count());
    }

    public function test_depois_de_expirar_o_caixa_aceita_novo_login(): void
    {
        $this->abrirSessao();
        $this->travel(481)->minutes();

        $this->login()->assertCreated();

        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()->where('status', PdvOperatorSession::STATUS_ACTIVE)->count());
        $this->assertSame(2, PdvOperatorSession::withoutGlobalScopes()->count());
    }

    public function test_outro_operador_no_mesmo_caixa_recebe_conflito(): void
    {
        $this->abrirSessao();

        $this->operadorAutorizado(username: 'outro.caixa');

        $resposta = $this->login(payload: ['username' => 'outro.caixa', 'password' => self::SENHA]);

        $this->assertErroPdv($resposta, 409, OperatorSessionFailed::CODE_CONFLICT);
        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()->count());
        $this->assertSame($this->operador->id, PdvOperatorSession::withoutGlobalScopes()->sole()->user_id);
    }

    /**
     * Resposta perdida depois de criar a sessão: o Java repete o login com a
     * mesma senha. Não há segunda sessão, a jornada não é reiniciada, e a
     * credencial é NOVA — a antiga, que pode ter vazado no caminho, morre.
     */
    public function test_mesmo_operador_recupera_a_propria_sessao_com_credencial_rotacionada(): void
    {
        $primeira = $this->abrirSessao();
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->travel(30)->minutes();
        $resposta = $this->login();

        $resposta->assertOk()->assertJsonPath('data.session.id', $sessao->id);
        $segunda = $resposta->json('data.credential.access_token');

        $this->assertNotSame($primeira, $segunda);
        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()->count());

        $atualizada = PdvOperatorSession::withoutGlobalScopes()->sole();
        // Mesma sessão e mesmo início de jornada; só o prazo técnico desliza.
        $this->assertTrue($atualizada->authenticated_at->equalTo($sessao->authenticated_at));
        $this->assertNull($atualizada->absolute_expires_at);

        // A credencial antiga deixa de valer; a nova vale.
        $this->assertErroPdv($this->consultarSessao($primeira), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
        $this->consultarSessao($segunda)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::SESSION_RECOVERED,
            'user_id' => $this->operador->id,
        ]);
    }

    /**
     * A recuperação exige nova comprovação de identidade: senha errada no
     * retry não devolve a sessão nem rotaciona nada.
     */
    public function test_recuperacao_exige_senha_correta(): void
    {
        $credencial = $this->abrirSessao();
        $hash = PdvOperatorSession::withoutGlobalScopes()->sole()->credential_hash;

        $resposta = $this->login(payload: ['username' => $this->username, 'password' => 'errada-mas-longa']);

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_AUTHENTICATION);
        $this->assertSame($hash, PdvOperatorSession::withoutGlobalScopes()->sole()->credential_hash);
        $this->consultarSessao($credencial)->assertOk();
    }

    public function test_inativacao_do_operador_derruba_a_sessao_na_requisicao_seguinte(): void
    {
        $credencial = $this->abrirSessao();

        $this->operador->update(['status' => User::STATUS_INACTIVE]);

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
    }

    /**
     * Reativar a conta NÃO ressuscita a sessão: ela já foi revogada, e de
     * REVOKED não se volta. O caminho é novo login.
     */
    public function test_sessao_revogada_nao_volta_a_funcionar_ao_reativar_a_conta(): void
    {
        $credencial = $this->abrirSessao();

        $this->operador->update(['status' => User::STATUS_INACTIVE]);
        $this->consultarSessao($credencial)->assertStatus(401);
        $this->operador->update(['status' => User::STATUS_ACTIVE]);

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
    }

    public function test_suspensao_da_membership_derruba_a_sessao(): void
    {
        $credencial = $this->abrirSessao();

        $this->operador->joinTenant($this->tenant->id, TenantUser::STATUS_SUSPENDED);

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
    }

    public function test_revogacao_da_atribuicao_derruba_a_sessao(): void
    {
        $credencial = $this->abrirSessao();

        PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->where('user_id', $this->operador->id)
            ->update(['status' => PdvOperatorBranchAssignment::STATUS_REVOKED, 'revoked_at' => now()]);

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
    }

    public function test_remocao_da_permissao_derruba_a_sessao(): void
    {
        $credencial = $this->abrirSessao();

        $this->operador->removeRole(
            Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'pdv-operator')->sole(),
            $this->tenant->id,
        );

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
    }

    public function test_bloqueio_do_terminal_revoga_a_sessao_imediatamente(): void
    {
        $credencial = $this->abrirSessao();

        $this->terminal->update(['status' => Terminal::STATUS_BLOCKED]);

        // Revogada no ato pelo gancho, sem esperar requisição.
        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
        $this->assertErroPdv($this->consultarSessao($credencial), 401, 'unauthenticated');
    }

    /**
     * Rotação de rotina da credencial de máquina PRESERVA o turno.
     *
     * É a inversão deliberada da regra anterior. A sessão continua amarrada a um
     * PAT específico, mas a confirmação da rotação religa esse vínculo na mesma
     * transação que promove a candidata — então o operador não é interrompido por
     * uma renovação técnica que ele nem vê acontecer.
     */
    public function test_rotacao_de_rotina_da_maquina_preserva_a_sessao(): void
    {
        $credencial = $this->abrirSessao();
        $antes = PdvOperatorSession::withoutGlobalScopes()->sole();

        $nova = $this->rotacionarMaquina();

        $this->consultarSessao($credencial, $nova)->assertOk()
            ->assertJsonPath('data.session.id', $antes->id)
            ->assertJsonPath('data.session.status', PdvOperatorSession::STATUS_ACTIVE)
            ->assertJsonPath('data.session.machine_rotations', 1);

        $depois = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertNotSame((int) $antes->machine_token_id, (int) $depois->machine_token_id);
        $this->assertSame((int) $this->terminal->fresh()->tokens()->sole()->id, (int) $depois->machine_token_id);
        // A jornada não é reiniciada por uma renovação técnica.
        $this->assertTrue($depois->authenticated_at->equalTo($antes->authenticated_at));

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::SESSION_MACHINE_ROTATED,
            'user_id' => $this->operador->id,
        ]);
    }

    /**
     * A credencial de máquina ANTERIOR deixa de valer depois da rotação, e a
     * sessão não pode ser usada com ela.
     */
    public function test_credencial_de_maquina_anterior_nao_opera_a_sessao_depois_da_rotacao(): void
    {
        $credencial = $this->abrirSessao();
        $antiga = $this->maquina;

        $this->rotacionarMaquina();

        $this->assertErroPdv($this->consultarSessao($credencial, $antiga), 401, 'unauthenticated');
    }

    /**
     * Recuperação administrativa NÃO herda a sessão.
     *
     * Ela nasce de perda, suspeita de comprometimento ou substituição física de
     * equipamento. Nesses casos a credencial antiga é revogada, a amarração se
     * rompe e o operador precisa se autenticar de novo — é uma decisão de
     * segurança, não um efeito colateral.
     */
    public function test_recuperacao_administrativa_exige_novo_login_do_operador(): void
    {
        $credencial = $this->abrirSessao();

        $nova = $this->recuperarMaquinaPorAdministracao();

        $this->assertErroPdv(
            $this->consultarSessao($credencial, $nova),
            401,
            OperatorSessionFailed::CODE_SESSION_INVALID,
        );

        $this->assertNotSame(
            PdvOperatorSession::STATUS_ACTIVE,
            PdvOperatorSession::withoutGlobalScopes()->sole()->status,
        );
    }

    /**
     * Logout continua funcionando depois da rotação da máquina: fechar turno é
     * sempre a direção segura, e o cliente precisa poder limpar o estado local.
     */
    public function test_logout_funciona_depois_da_rotacao_da_maquina(): void
    {
        $credencial = $this->abrirSessao();
        $nova = $this->rotacionarMaquina();

        $this->logout($credencial, $nova)->assertNoContent();

        $this->assertSame(PdvOperatorSession::STATUS_ENDED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
    }

    public function test_credencial_de_sessao_nao_vale_em_outro_terminal(): void
    {
        $credencial = $this->abrirSessao();

        $outro = $this->terminalOperacional($this->branch);

        $this->assertErroPdv(
            $this->consultarSessao($credencial, $this->credencialDeMaquina($outro)),
            401,
            OperatorSessionFailed::CODE_SESSION_INVALID,
        );
    }

    /**
     * Instalação divergente recusa.
     *
     * O domínio impede trocar o `installation_id` de um Terminal já instalado
     * (TerminalAssignmentValidator), então o cenário é construído mexendo na
     * LINHA DA SESSÃO por fora do Eloquent — que é a forma de uma divergência
     * aparecer de verdade: dump restaurado, escrita direta, linha herdada de
     * uma instalação anterior. A amarração é conferida por requisição, e não
     * depende de nenhum hook ter rodado.
     */
    public function test_credencial_de_sessao_nao_vale_com_instalacao_divergente(): void
    {
        $credencial = $this->abrirSessao();

        DB::table('pdv_operator_sessions')->update(['installation_id' => (string) Str::uuid()]);

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
    }

    /**
     * Mesma prova para o id do PAT de máquina: uma sessão cuja linha aponta
     * para outra credencial não autentica, mesmo com tudo o mais coerente.
     */
    public function test_credencial_de_sessao_nao_vale_com_outra_credencial_de_maquina_na_linha(): void
    {
        $credencial = $this->abrirSessao();

        DB::table('pdv_operator_sessions')->update(['machine_token_id' => 999999]);

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);
    }

    public function test_credencial_de_sessao_so_e_aceita_em_cabecalho(): void
    {
        $credencial = $this->abrirSessao();

        $this->novaRequisicao();
        $resposta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($this->maquina)
            ->getJson('/api/v1/pdv/operator-session?operator_session='.$credencial);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_sessao_ausente_recusa_a_rota_protegida(): void
    {
        $this->abrirSessao();

        $this->novaRequisicao();
        $resposta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($this->maquina)
            ->getJson('/api/v1/pdv/operator-session');

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_SESSION_INVALID);
    }

    public function test_credencial_malformada_recebe_a_mesma_recusa_da_inexistente(): void
    {
        $this->abrirSessao();

        $malformada = $this->consultarSessao('nao-e-hex');
        $inexistente = $this->consultarSessao(str_repeat('b', 64));

        foreach ([$malformada, $inexistente] as $resposta) {
            $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_SESSION_INVALID);
        }

        $this->assertSame(
            $this->semRequestId($malformada->json('error')),
            $this->semRequestId($inexistente->json('error')),
        );
    }

    public function test_capacidades_nao_incluem_operacao_comercial(): void
    {
        $credencial = $this->abrirSessao();

        $capacidades = $this->consultarSessao($credencial)->assertOk()->json('data.capabilities');

        $this->assertSame(['pdv:operator:session'], $capacidades);

        foreach (['sale', 'venda', 'discount', 'payment', 'cancel', 'withdrawal', 'close'] as $proibida) {
            foreach ($capacidades as $capacidade) {
                $this->assertStringNotContainsString($proibida, $capacidade);
            }
        }
    }

    /**
     * Recuperação administrativa completa, pelos endpoints reais.
     *
     * Devolve a credencial de máquina promovida. A diferença em relação à
     * rotação de rotina está no caminho, não no resultado aparente: a
     * autorização do código já revoga os PATs do terminal, e a confirmação
     * desta operação é RECOVERY — então o listener não religa sessão nenhuma.
     */
    private function recuperarMaquinaPorAdministracao(): string
    {
        $admin = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $admin);
        $tokenHumano = $admin->fresh()->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;

        $this->novaRequisicao();
        $codigo = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->withToken($tokenHumano)
            ->postJson("/api/v1/pdv/admin/terminals/{$this->terminal->id}/credential-recovery-codes", ['reason' => 'lost'])
            ->assertOk()
            ->json('data.recovery_code');

        $this->novaRequisicao();
        $candidata = $this->postJson('/api/v1/pdv/terminal/credential/recover', [
            'recovery_code' => $codigo,
            'installation_id' => $this->terminal->fresh()->installation_id,
            'delivery_secret' => bin2hex(random_bytes(32)),
        ])->assertOk()->json('data.credential.access_token');

        $this->novaRequisicao();
        $this->withToken($candidata)
            ->postJson('/api/v1/pdv/terminal/credential/confirm', [
                'operation_id' => TerminalCredentialOperation::withoutGlobalScopes()
                    ->where('terminal_id', $this->terminal->id)
                    ->where('kind', 'RECOVERY')
                    ->value('id'),
            ])->assertOk();

        return $candidata;
    }

    /**
     * @param  array<string, mixed>  $erro
     * @return array<string, mixed>
     */
    private function semRequestId(array $erro): array
    {
        unset($erro['request_id']);

        return $erro;
    }
}
