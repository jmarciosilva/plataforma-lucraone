<?php

namespace Tests\Feature\Pdv;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorSessionAudit;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

/**
 * Jornada operacional contínua.
 *
 * O requisito do produto é simples de dizer e fácil de implementar errado: um
 * comerciante abre a loja às sete, autentica no caixa e trabalha até fechar,
 * quinze horas depois, sem ser interrompido por um relógio. E, ao mesmo tempo,
 * nenhuma credencial operacional pode ser eterna.
 *
 * A solução testada aqui separa os dois prazos:
 *
 *   - a credencial tem validade TÉCNICA finita que DESLIZA com o uso legítimo;
 *   - a jornada não tem teto fixo, e o que a encerra é logout, revogação, perda
 *     de autorização ou — quando configurado — o teto explícito de jornada.
 *
 * O ponto mais importante é o negativo: deslizar não é renovar autorização.
 * Vários testes daqui confirmam que uma sessão que perdeu autorização não ganha
 * prazo nenhum, por mais que seja usada.
 */
#[Group('pdv-operator')]
#[Group('pdv-continuidade')]
class PdvOperatorContinuityTest extends PdvOperatorTestCase
{
    /**
     * Oito horas depois — o limite da versão anterior —, o caixa continua
     * aberto, com uso normal pelo caminho.
     */
    public function test_operador_continua_logado_depois_de_oito_horas(): void
    {
        $credencial = $this->abrirSessao();
        $inicio = PdvOperatorSession::withoutGlobalScopes()->sole()->authenticated_at;

        foreach ([180, 180, 125] as $minutos) {
            $this->travel($minutos)->minutes();
            $this->consultarSessao($credencial)->assertOk();
        }

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertSame(PdvOperatorSession::STATUS_ACTIVE, $sessao->status);
        $this->assertGreaterThanOrEqual(485, $inicio->diffInMinutes(now()));
        // A jornada não foi reiniciada: é o MESMO turno, desde o mesmo login.
        $this->assertTrue($sessao->authenticated_at->equalTo($inicio));
    }

    /**
     * Quinze horas, com revalidações online e UMA rotação técnica da credencial
     * de máquina pelo caminho.
     *
     * A rotação é obrigatória num turno longo, e não um detalhe do teste: a
     * credencial de máquina tem teto de 720 minutos imposto pelo Sanctum, então
     * uma jornada de quinze horas a atravessa necessariamente. É justamente o
     * caso que a entrega anterior derrubava — e que agora é transparente para o
     * operador.
     */
    public function test_operador_continua_logado_depois_de_quinze_horas_com_revalidacoes(): void
    {
        $credencial = $this->abrirSessao();
        $inicio = PdvOperatorSession::withoutGlobalScopes()->sole()->authenticated_at;

        for ($hora = 0; $hora < 15; $hora++) {
            $this->travel(60)->minutes();

            // Na décima hora o cliente renova a credencial técnica, como o
            // contrato recomenda — antes do vencimento, não depois.
            if ($hora === 9) {
                $this->maquina = $this->rotacionarMaquina();
            }

            $this->revalidar($credencial)->assertOk();
        }

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertSame(PdvOperatorSession::STATUS_ACTIVE, $sessao->status);
        $this->assertSame(900, (int) $inicio->diffInMinutes(now()));
        $this->assertTrue($sessao->authenticated_at->equalTo($inicio));
        $this->assertSame(1, $sessao->machine_rotations);
        // Uma sessão, do começo ao fim do dia.
        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()->count());
    }

    /**
     * Sem uso, a credencial vence pela janela deslizante.
     *
     * É o que impede o token eterno: um caixa deixado ligado e esquecido para de
     * valer sozinho, sem precisar de ninguém para encerrá-lo.
     */
    public function test_sem_uso_a_credencial_vence_pela_janela_deslizante(): void
    {
        $credencial = $this->abrirSessao();
        $janela = (int) config('pdv.operator_sessions.sliding_minutes');

        $this->travel($janela + 1)->minutes();

        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $this->assertSame(PdvOperatorSession::STATUS_EXPIRED, $sessao->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::SESSION_EXPIRED,
            'user_id' => $this->operador->id,
        ]);
    }

    /**
     * O uso dentro da janela a renova; depois dela, não há o que renovar.
     */
    public function test_uso_dentro_da_janela_renova_e_depois_dela_nao(): void
    {
        $credencial = $this->abrirSessao();
        $janela = (int) config('pdv.operator_sessions.sliding_minutes');

        $this->travel($janela - 10)->minutes();
        $this->consultarSessao($credencial)->assertOk();

        // Renovada: outra janela quase inteira à frente.
        $this->travel($janela - 10)->minutes();
        $this->consultarSessao($credencial)->assertOk();

        // Agora o silêncio passa da janela.
        $this->travel($janela + 1)->minutes();
        $this->consultarSessao($credencial)->assertStatus(401);
    }

    /**
     * O deslizamento tem freio de gravação: requisições em rajada não viram um
     * UPDATE cada. O prazo percebido não encurta por causa do freio.
     */
    public function test_deslizamento_tem_freio_de_gravacao(): void
    {
        $credencial = $this->abrirSessao();
        $antes = PdvOperatorSession::withoutGlobalScopes()->sole();

        // Dentro do freio: nada é gravado.
        $this->travel(1)->minutes();
        $this->consultarSessao($credencial)->assertOk();
        $this->assertTrue(PdvOperatorSession::withoutGlobalScopes()->sole()->expires_at->equalTo($antes->expires_at));

        // Além do freio: grava.
        $this->travel((int) config('pdv.operator_sessions.slide_threshold_minutes') + 1)->minutes();
        $this->consultarSessao($credencial)->assertOk();
        $this->assertTrue(PdvOperatorSession::withoutGlobalScopes()->sole()->expires_at->greaterThan($antes->expires_at));
    }

    /**
     * Revogada, a sessão não ganha prazo por mais que seja usada.
     *
     * É a propriedade que separa "renovação técnica" de "renovação de
     * autorização". O deslizamento acontece DEPOIS da revalidação completa,
     * nunca antes.
     */
    public function test_sessao_revogada_nao_e_estendida_pelo_uso(): void
    {
        $credencial = $this->abrirSessao();
        $antes = PdvOperatorSession::withoutGlobalScopes()->sole()->expires_at;

        PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->update(['status' => PdvOperatorBranchAssignment::STATUS_REVOKED, 'revoked_at' => now()]);

        $this->travel(30)->minutes();
        $this->consultarSessao($credencial)->assertStatus(401);

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, $sessao->status);
        $this->assertTrue($sessao->expires_at->equalTo($antes), 'sessão revogada não pode deslizar.');
    }

    public function test_operador_desativado_nao_e_estendido_pelo_uso(): void
    {
        $credencial = $this->abrirSessao();
        $antes = PdvOperatorSession::withoutGlobalScopes()->sole()->expires_at;

        PdvOperatorProfile::withoutGlobalScopes()->update(['status' => PdvOperatorProfile::STATUS_INACTIVE]);

        $this->travel(30)->minutes();
        $this->consultarSessao($credencial)->assertStatus(401);

        $this->assertTrue(PdvOperatorSession::withoutGlobalScopes()->sole()->expires_at->equalTo($antes));
    }

    public function test_permissao_removida_nao_e_estendida_pelo_uso(): void
    {
        $credencial = $this->abrirSessao();

        $this->operador->removeRole(
            Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'pdv-operator')->sole(),
            $this->tenant->id,
        );

        $this->travel(30)->minutes();
        $this->consultarSessao($credencial)->assertStatus(401);

        $this->assertSame(PdvOperatorSession::STATUS_REVOKED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
    }

    public function test_conta_desativada_nao_e_estendida_pelo_uso(): void
    {
        $credencial = $this->abrirSessao();

        $this->operador->update(['status' => User::STATUS_INACTIVE]);

        $this->travel(30)->minutes();
        $this->consultarSessao($credencial)->assertStatus(401);
    }

    /**
     * Renovar o prazo técnico não amplia capacidades nem permissões.
     *
     * Um turno longo com muitas renovações continua com a MESMA lista de
     * capacidades do primeiro minuto — nada de venda, desconto, pagamento,
     * sangria ou fechamento aparece por tempo de casa.
     */
    public function test_renovacao_tecnica_nao_amplia_capacidades(): void
    {
        $credencial = $this->abrirSessao();
        $primeiras = $this->consultarSessao($credencial)->assertOk()->json('data.capabilities');

        for ($hora = 0; $hora < 10; $hora++) {
            $this->travel(60)->minutes();

            if ($hora === 8) {
                $this->maquina = $this->rotacionarMaquina();
            }

            $this->revalidar($credencial)->assertOk();
        }

        $ultimas = $this->consultarSessao($credencial)->assertOk()->json('data.capabilities');

        $this->assertSame(['pdv:operator:session'], $primeiras);
        $this->assertSame($primeiras, $ultimas);
    }

    /**
     * Com teto de jornada configurado, a continuidade respeita o teto.
     *
     * A política do piloto é sem teto, mas quem quiser um precisa conseguir
     * impô-lo — e nenhuma renovação pode passar dele.
     */
    public function test_teto_de_jornada_configurado_encerra_a_jornada(): void
    {
        config(['pdv.operator_sessions.max_minutes' => 120]);

        $credencial = $this->abrirSessao();
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertNotNull($sessao->absolute_expires_at);

        $this->travel(60)->minutes();
        $this->revalidar($credencial)->assertOk();

        $this->travel(61)->minutes();
        $this->assertErroPdv($this->consultarSessao($credencial), 401, OperatorSessionFailed::CODE_SESSION_INVALID);

        $this->assertSame(PdvOperatorSession::STATUS_EXPIRED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => OperatorSessionAudit::SESSION_EXPIRED,
        ]);
    }

    /**
     * Depois do teto, o caixa aceita login novo — a loja não trava.
     */
    public function test_depois_do_teto_o_caixa_aceita_novo_login(): void
    {
        config(['pdv.operator_sessions.max_minutes' => 60]);

        $this->abrirSessao();
        $this->travel(61)->minutes();

        $this->login()->assertCreated();

        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)->count());
    }

    /**
     * A política publicada no contrato diz ao cliente o que esperar.
     */
    public function test_contrato_publica_a_politica_de_jornada_e_de_bloqueio_de_tela(): void
    {
        $resposta = $this->login()->assertCreated();

        $resposta->assertJsonPath('data.policy.screen_lock_minutes', null)
            ->assertJsonPath('data.policy.sliding_minutes', (int) config('pdv.operator_sessions.sliding_minutes'))
            ->assertJsonPath('data.session.absolute_expires_at', null);
    }

    /**
     * Bloqueio de tela é política do estabelecimento, publicada e aplicada pelo
     * CLIENTE. Ligar não encerra sessão nem fecha caixa.
     */
    public function test_bloqueio_de_tela_configurado_aparece_no_contrato_sem_encerrar_sessao(): void
    {
        DB::table('pdv_tenant_settings')->insert([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'screen_lock_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $credencial = $this->abrirSessao();

        $this->consultarSessao($credencial)->assertOk()
            ->assertJsonPath('data.policy.screen_lock_minutes', 15)
            ->assertJsonPath('data.session.status', PdvOperatorSession::STATUS_ACTIVE);

        // Passado o tempo de bloqueio, a sessão continua valendo: bloquear tela
        // é assunto do aplicativo, não do servidor.
        $this->travel(20)->minutes();
        $this->consultarSessao($credencial)->assertOk();
    }

    /**
     * Logout continua encerrando o turno, por mais longo que ele seja.
     */
    public function test_logout_encerra_a_jornada_longa(): void
    {
        $credencial = $this->abrirSessao();

        for ($hora = 0; $hora < 12; $hora++) {
            $this->travel(60)->minutes();

            if ($hora === 9) {
                $this->maquina = $this->rotacionarMaquina();
            }

            $this->revalidar($credencial)->assertOk();
        }

        $this->logout($credencial)->assertNoContent();

        $this->assertSame(PdvOperatorSession::STATUS_ENDED, PdvOperatorSession::withoutGlobalScopes()->sole()->status);
        $this->consultarSessao($credencial)->assertStatus(401);
    }

    /**
     * Troca de operador durante o dia: o novo só entra depois do logout do
     * anterior, e a jornada do novo começa do zero.
     */
    public function test_troca_de_operador_durante_a_jornada(): void
    {
        $primeira = $this->abrirSessao();
        $this->operadorAutorizado(username: 'segundo.turno');

        // Cinco horas de turno, com uso dentro da janela deslizante e uma
        // rotação técnica pelo caminho.
        $this->travel(180)->minutes();
        $this->consultarSessao($primeira)->assertOk();
        $this->travel(120)->minutes();
        $this->maquina = $this->rotacionarMaquina();
        $this->consultarSessao($primeira)->assertOk();

        // Caixa ocupado: o segundo operador não entra por cima do primeiro.
        $this->assertErroPdv(
            $this->login(payload: ['username' => 'segundo.turno', 'password' => self::SENHA]),
            409,
            OperatorSessionFailed::CODE_CONFLICT,
        );

        $this->logout($primeira)->assertNoContent();

        $segunda = $this->login(payload: ['username' => 'segundo.turno', 'password' => self::SENHA])
            ->assertCreated()
            ->json('data.credential.access_token');

        $this->consultarSessao($segunda)->assertOk();
        $this->assertSame(2, PdvOperatorSession::withoutGlobalScopes()->count());
        $this->assertSame(1, PdvOperatorSession::withoutGlobalScopes()
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)->count());
    }
}
