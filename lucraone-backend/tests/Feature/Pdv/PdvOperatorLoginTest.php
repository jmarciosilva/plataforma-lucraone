<?php

namespace Tests\Feature\Pdv;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorSessionAudit;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Fase 5 — autenticação do operador no caixa.
 *
 * A propriedade central é que SETE condições precisam valer juntas e que a
 * recusa é a MESMA para todas: senha, existência da conta, estado da conta,
 * vínculo, permissão, atribuição à filial e estabelecimento. Um contrato que
 * distinguisse esses casos seria um oráculo sobre quem tem conta no LucraOne,
 * quem continua empregado e em qual loja trabalha.
 */
#[Group('pdv-operator')]
class PdvOperatorLoginTest extends PdvOperatorTestCase
{
    public function test_login_valido_abre_sessao_e_entrega_credencial_uma_vez(): void
    {
        $resposta = $this->login();

        $resposta->assertCreated()
            ->assertJsonPath('data.session.status', PdvOperatorSession::STATUS_ACTIVE)
            ->assertJsonPath('data.operator.id', $this->operador->id)
            ->assertJsonPath('data.operator.username', $this->username)
            ->assertJsonPath('data.context.terminal.id', $this->terminal->id)
            ->assertJsonPath('data.context.branch.id', $this->branch->id)
            ->assertJsonStructure(['data' => [
                'session' => ['id', 'status', 'authenticated_at', 'last_revalidated_at', 'expires_at'],
                'credential' => ['token_type', 'access_token', 'expires_at'],
                'operator' => ['id', 'name', 'username', 'email'],
                'context' => ['terminal', 'tenant', 'company', 'branch'],
                'capabilities',
                'policy' => ['screen_lock_minutes', 'sliding_minutes'],
                'offline' => ['enabled'],
            ]]);

        $this->assertSame('no-store, private', $resposta->headers->get('Cache-Control'));

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $credencial = $resposta->json('data.credential.access_token');

        // Entregue em texto puro uma única vez; o banco guarda só o hash.
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $credencial);
        $this->assertSame(hash('sha256', $credencial), $sessao->credential_hash);
        $this->assertStringNotContainsString($credencial, json_encode($sessao->toArray()));

        // A sessão nasce amarrada às duas identidades e ao contexto do caixa.
        $this->assertSame($this->terminal->id, $sessao->terminal_id);
        $this->assertSame($this->terminal->installation_id, $sessao->installation_id);
        $this->assertSame($this->operador->id, $sessao->user_id);
        $this->assertSame($this->branch->id, $sessao->branch_id);
        $this->assertSame($this->tenant->id, $sessao->tenant_id);
        $this->assertNotNull($sessao->machine_token_id);
    }

    /**
     * A sessão nasce com prazo TÉCNICO deslizante e SEM teto de jornada.
     *
     * É a diferença central em relação à primeira versão, que gravava oito
     * horas absolutas e encerrava o turno no meio da tarde.
     */
    public function test_sessao_nasce_com_prazo_deslizante_e_sem_teto_de_jornada(): void
    {
        $this->login()->assertCreated();

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $deslizante = (int) config('pdv.operator_sessions.sliding_minutes');

        $this->assertSame(0, (int) config('pdv.operator_sessions.max_minutes'), 'o piloto não tem teto de jornada.');
        $this->assertNull($sessao->absolute_expires_at);
        $this->assertTrue($sessao->expires_at->greaterThan(now()->addMinutes($deslizante - 1)));
        $this->assertTrue($sessao->expires_at->lessThanOrEqualTo(now()->addMinutes($deslizante)));
        $this->assertSame(0, $sessao->machine_rotations);
    }

    /**
     * O prazo da sessão já NÃO é limitado pela expiração da credencial de
     * máquina. Antes era, e isso fazia um caixa com credencial vencendo em
     * vinte minutos abrir um turno de vinte minutos.
     */
    public function test_prazo_da_sessao_nao_e_mais_limitado_pela_credencial_de_maquina(): void
    {
        $this->terminal->tokens()->update(['expires_at' => now()->addMinutes(20)]);
        $this->maquina = $this->credencialDeMaquina($this->terminal);
        $this->terminal->tokens()->update(['expires_at' => now()->addMinutes(20)]);

        $this->login()->assertCreated();

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertTrue($sessao->expires_at->greaterThan(now()->addMinutes(30)));
    }

    /**
     * As sete recusas que precisam ser indistinguíveis.
     *
     * @return array<string, array{0: string}>
     */
    public static function recusasDeAutenticacao(): array
    {
        return [
            'senha inválida' => ['senhaInvalida'],
            'username inexistente' => ['usuarioInexistente'],
            'conta inativa' => ['contaInativa'],
            'membership suspensa' => ['membershipSuspensa'],
            'sem permissão access-pdv' => ['semPermissao'],
            'sem atribuição de filial' => ['semAtribuicao'],
            'atribuição revogada' => ['atribuicaoRevogada'],
            'perfil de caixa inativo' => ['perfilInativo'],
            'sem perfil de caixa' => ['semPerfil'],
        ];
    }

    #[DataProvider('recusasDeAutenticacao')]
    public function test_recusa_de_autenticacao_e_identica_para_todas_as_causas(string $cenario): void
    {
        $payload = $this->$cenario();

        $resposta = $this->login(payload: $payload);

        $this->assertErroPdv($resposta, 401, OperatorSessionFailed::CODE_AUTHENTICATION);
        $resposta->assertJsonPath('error.message', 'Não foi possível autenticar o operador.');
        $this->assertSame(0, PdvOperatorSession::withoutGlobalScopes()->count());
    }

    /**
     * Mensagem e status iguais não bastam: duas causas com corpos diferentes
     * ainda diferenciariam. Comparar o corpo inteiro, menos o request_id,
     * prova que não sobrou nenhum campo discriminante.
     */
    public function test_corpos_das_recusas_sao_byte_a_byte_iguais(): void
    {
        $corpos = [];

        foreach (['senhaInvalida', 'usuarioInexistente', 'semAtribuicao'] as $cenario) {
            $resposta = $this->login(payload: $this->$cenario());
            $erro = $resposta->json('error');
            unset($erro['request_id']);
            $corpos[] = $erro;
        }

        $this->assertCount(1, array_unique(array_map('json_encode', $corpos)));
    }

    /**
     * A tentativa recusada registra o USERNAME tentado — que é operacional e
     * aparece na tela do administrador — e nunca a senha nem o e-mail pessoal.
     */
    public function test_tentativa_recusada_e_auditada_com_username_e_sem_senha(): void
    {
        $this->login(payload: ['username' => $this->username, 'password' => 'errada-mas-longa'])
            ->assertStatus(401);

        $registro = AuditLog::withoutGlobalScopes()
            ->where('action', OperatorSessionAudit::LOGIN_FAILED)
            ->sole();

        // A pessoa é identificada quando o username existe — a auditoria humana
        // não pode atribuir a ação à máquina.
        $this->assertSame($this->operador->id, $registro->user_id);
        $this->assertSame($this->tenant->id, $registro->tenant_id);
        $this->assertSame('credentials', $registro->changes['reason']);
        $this->assertSame('caixateste', $registro->changes['attempted_username']);

        $serializado = json_encode($registro->toArray());
        $this->assertStringNotContainsString((string) $this->operador->email, $serializado);
        $this->assertStringNotContainsString('errada-mas-longa', $serializado);
        $this->assertStringNotContainsString(self::SENHA, $serializado);
    }

    /**
     * Username inexistente também é auditado, para o lojista ver insistência
     * contra nomes que não existem.
     */
    public function test_username_inexistente_tambem_e_auditado(): void
    {
        $this->login(payload: ['username' => 'fantasma', 'password' => self::SENHA])->assertStatus(401);

        $registro = AuditLog::withoutGlobalScopes()
            ->where('action', OperatorSessionAudit::LOGIN_FAILED)
            ->sole();

        $this->assertNull($registro->user_id);
        $this->assertSame('fantasma', $registro->changes['attempted_username']);
    }

    public function test_login_bem_sucedido_audita_pessoa_e_nao_a_maquina(): void
    {
        $this->login()->assertCreated();

        foreach ([OperatorSessionAudit::LOGIN, OperatorSessionAudit::SESSION_STARTED] as $acao) {
            $registro = AuditLog::withoutGlobalScopes()->where('action', $acao)->sole();

            $this->assertSame($this->operador->id, $registro->user_id);
            $this->assertNotSame($this->terminal->id, $registro->user_id);
            $this->assertSame($this->terminal->id, $registro->changes['terminal_id']);
        }
    }

    public function test_senha_nunca_aparece_em_log(): void
    {
        Log::spy();

        $this->login()->assertCreated();
        $this->login(payload: ['username' => $this->username, 'password' => 'outra-senha-x'])->assertStatus(401);

        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log', 'write'] as $metodo) {
            Log::shouldNotHaveReceived($metodo);
        }
    }

    public function test_terminal_bloqueado_nao_autentica_operador(): void
    {
        $this->terminal->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->assertErroPdv($this->login(), 401, 'unauthenticated');
    }

    public function test_terminal_revogado_nao_autentica_operador(): void
    {
        $this->terminal->update(['status' => Terminal::STATUS_REVOKED]);

        $this->assertErroPdv($this->login(), 401, 'unauthenticated');
    }

    public function test_credencial_de_maquina_invalida_nao_autentica_operador(): void
    {
        $this->assertErroPdv($this->login('credencial-que-nao-existe'), 401, 'unauthenticated');
    }

    public function test_credencial_de_maquina_expirada_nao_autentica_operador(): void
    {
        $this->terminal->tokens()->update(['expires_at' => now()->subMinute()]);

        $this->assertErroPdv($this->login(), 401, 'unauthenticated');
    }

    /**
     * Credencial legada só tem `pdv:terminal:read`: ela autentica a máquina,
     * mas não alcança o fluxo de operador. O caminho oficial é rotacionar.
     */
    public function test_credencial_legada_nao_alcanca_o_fluxo_de_operador(): void
    {
        $this->terminal->tokens()->delete();
        $legada = $this->terminal->createToken('pdv-machine', ['pdv:terminal:read'], now()->addHour());

        $this->assertErroPdv($this->login($legada->plainTextToken), 403, 'forbidden');
    }

    public function test_candidata_de_rotacao_nao_autentica_operador(): void
    {
        $this->terminal->tokens()->delete();
        $candidata = $this->terminal->createToken('pdv-machine-pending', ['pdv:credential:confirm'], now()->addMinutes(5));

        $this->assertErroPdv($this->login($candidata->plainTextToken), 403, 'forbidden');
    }

    public function test_token_humano_nao_autentica_em_rota_de_maquina(): void
    {
        $humano = $this->operador->createToken('auth_token', ['business:read', 'business:write', 'pdv:operator-session']);

        $this->assertErroPdv($this->login($humano->plainTextToken), 403, 'forbidden');
    }

    public function test_cabecalho_de_tenant_e_recusado_na_rota_de_maquina(): void
    {
        $resposta = $this->login(cabecalhos: ['X-Tenant-ID' => $this->tenant->id]);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_corpo_nao_escolhe_terminal_empresa_nem_filial(): void
    {
        $outro = $this->terminalOperacional();

        $resposta = $this->login(payload: [
            'username' => $this->username,
            'password' => self::SENHA,
            'terminal_id' => $outro->id,
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertSame(0, PdvOperatorSession::withoutGlobalScopes()->count());
    }

    /**
     * Força bruta numa conta, num caixa: o freio por identificador + terminal
     * é 5 por minuto e para antes do sexto.
     */
    public function test_forca_bruta_na_mesma_conta_e_freada(): void
    {
        $payload = ['username' => $this->username, 'password' => 'errada-mas-longa'];

        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->login(payload: $payload)->assertStatus(401);
        }

        $resposta = $this->login(payload: $payload);

        $this->assertErroPdv($resposta, 429, 'rate_limited');
        $this->assertNotEmpty($resposta->headers->get('Retry-After'));
    }

    /**
     * O freio não libera o acerto seguinte. Um limitador que deixasse a senha
     * correta passar depois de estourado não freia nada.
     */
    public function test_freio_vale_tambem_para_a_senha_correta(): void
    {
        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->login(payload: ['username' => $this->username, 'password' => 'errada-mas-longa'])
                ->assertStatus(401);
        }

        $this->assertErroPdv($this->login(), 429, 'rate_limited');
    }

    /**
     * Tentativa DISTRIBUÍDA: um caixa por tentativa derrota o freio por
     * terminal e o freio por conta+terminal. Só o agregado por identificador
     * enxerga esse padrão, e ele para na décima.
     */
    public function test_tentativa_distribuida_na_mesma_conta_e_freada_pelo_agregado(): void
    {
        $payload = ['username' => $this->username, 'password' => 'errada-mas-longa'];
        $recusas = 0;

        for ($tentativa = 1; $tentativa <= 11; $tentativa++) {
            $terminal = $this->terminalOperacional($this->branch);
            $resposta = $this->login($this->credencialDeMaquina($terminal), $payload);

            if ($resposta->status() === 429) {
                $recusas++;
            }
        }

        $this->assertGreaterThan(0, $recusas, 'O agregado por identificador precisa frear a tentativa distribuída.');
    }

    public function test_validacao_de_estrutura_nao_eco_do_valor_enviado(): void
    {
        $resposta = $this->login(payload: ['username' => str_repeat('x', 100), 'password' => '1']);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertStringNotContainsString(str_repeat('x', 100), $resposta->getContent());
    }

    /**
     * Qualquer grafia equivalente entra na mesma conta.
     *
     * É a razão de existir da forma canônica: quem está no caixa não deveria
     * perder uma venda porque digitou `Marcio` em vez de `marcio`.
     */
    public function test_grafias_equivalentes_do_username_autenticam(): void
    {
        $perfil = PdvOperatorProfile::withoutGlobalScopes()->where('user_id', $this->operador->id)->sole();
        $perfil->definirUsername('jose.silva');
        $perfil->save();

        // Quatro grafias, e não mais: o freio por identificador é 5 por minuto e
        // conta a forma CANÔNICA, então as grafias dividem o mesmo contador —
        // propriedade verificada no teste seguinte.
        foreach (['jose.silva', 'JOSE.SILVA', 'josesilva', 'José Silva'] as $grafia) {
            $resposta = $this->login(payload: ['username' => $grafia, 'password' => self::SENHA]);

            $resposta->assertSuccessful()->assertJsonPath('data.operator.id', $this->operador->id);
            $this->logout($resposta->json('data.credential.access_token'))->assertNoContent();
        }

        // Todas as sessões foram da MESMA pessoa, e nunca houve duas abertas ao
        // mesmo tempo — cada login veio depois do logout do anterior.
        $this->assertSame(
            [$this->operador->id],
            PdvOperatorSession::withoutGlobalScopes()->pluck('user_id')->unique()->values()->all(),
        );
        $this->assertSame(0, PdvOperatorSession::withoutGlobalScopes()
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)->count());
    }

    /**
     * O freio por conta conta a forma canônica.
     *
     * Se contasse o valor cru, um atacante trocaria `marcio`, `marcio.`,
     * `MARCIO` e `mar.cio` e ganharia cinco tentativas para cada grafia da mesma
     * conta — o freio por conta valeria o que ele quisesse.
     */
    public function test_freio_por_conta_nao_e_contornado_trocando_a_grafia(): void
    {
        $grafias = ['caixa.teste', 'CAIXATESTE', 'caixa_teste', 'caixa-teste', 'Caixa Teste'];

        foreach ($grafias as $grafia) {
            $this->login(payload: ['username' => $grafia, 'password' => 'errada-mas-longa'])
                ->assertStatus(401);
        }

        // Sexta tentativa, grafia nova: o contador é o mesmo.
        $this->assertErroPdv(
            $this->login(payload: ['username' => 'cAiXa.TeStE', 'password' => 'errada-mas-longa']),
            429,
            'rate_limited',
        );
    }

    /**
     * O username é resolvido DENTRO do estabelecimento do Terminal.
     *
     * Duas lojas podem ter cada uma o seu `caixa.teste`, e o caixa de uma nunca
     * autentica a pessoa da outra.
     */
    public function test_username_igual_em_outro_estabelecimento_nao_autentica(): void
    {
        $outraFilial = Branch::factory()->create(['status' => 'ACTIVE']);
        $outroTenant = Tenant::withoutGlobalScopes()->findOrFail($outraFilial->tenant_id);
        $outroTenant->update(['status' => 'ACTIVE', 'active' => true]);
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($outroTenant);

        // Mesmo username, senha DIFERENTE, outra loja.
        $vizinho = User::factory()->create(['password' => 'senha-do-vizinho-999']);
        $vizinho->joinTenant($outroTenant->id, TenantUser::STATUS_ACTIVE);
        $this->concederPermissaoDePdv($vizinho, $outroTenant);
        $this->atribuirFilial($vizinho, $outraFilial);
        $this->perfilDeOperador($vizinho, $outroTenant, $this->username);

        // A senha do vizinho não entra no caixa desta loja.
        $this->assertErroPdv(
            $this->login(payload: ['username' => $this->username, 'password' => 'senha-do-vizinho-999']),
            401,
            OperatorSessionFailed::CODE_AUTHENTICATION,
        );

        // E a pessoa certa entra, com a sessão amarrada a ela.
        $this->login()->assertCreated()
            ->assertJsonPath('data.operator.id', $this->operador->id);

        $this->assertSame($this->operador->id, PdvOperatorSession::withoutGlobalScopes()->sole()->user_id);
    }

    /**
     * Username sem perfil de caixa não autentica, mesmo com todo o resto em
     * ordem. É o terceiro degrau da escada de status.
     */
    public function test_pessoa_sem_perfil_de_caixa_nao_autentica(): void
    {
        PdvOperatorProfile::withoutGlobalScopes()->delete();

        $this->assertErroPdv($this->login(), 401, OperatorSessionFailed::CODE_AUTHENTICATION);
    }

    public function test_perfil_de_caixa_inativo_nao_autentica(): void
    {
        PdvOperatorProfile::withoutGlobalScopes()->update(['status' => PdvOperatorProfile::STATUS_INACTIVE]);

        $this->assertErroPdv($this->login(), 401, OperatorSessionFailed::CODE_AUTHENTICATION);
    }

    /**
     * E-mail não é mais aceito no contrato do caixa. O login humano por e-mail
     * continua existindo em `/api/v1/auth/login` e no painel — portas
     * diferentes, para situações diferentes.
     */
    public function test_email_nao_e_aceito_no_contrato_do_caixa(): void
    {
        $resposta = $this->login(payload: [
            'email' => $this->operador->email,
            'password' => self::SENHA,
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertSame(0, PdvOperatorSession::withoutGlobalScopes()->count());
    }

    // ----------------------------------------------------------------
    // Cenários de recusa. Cada um remove UMA peça do login legítimo.
    // ----------------------------------------------------------------

    /** @return array<string, string> */
    private function senhaInvalida(): array
    {
        return ['username' => $this->username, 'password' => 'senha-que-nao-confere'];
    }

    /** @return array<string, string> */
    private function usuarioInexistente(): array
    {
        return ['username' => 'ninguem', 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function contaInativa(): array
    {
        $this->operador->update(['status' => User::STATUS_INACTIVE]);

        return ['username' => $this->username, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function membershipSuspensa(): array
    {
        $this->operador->joinTenant($this->tenant->id, TenantUser::STATUS_SUSPENDED);

        return ['username' => $this->username, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function semPermissao(): array
    {
        $this->operador->removeRole(
            Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'pdv-operator')->sole(),
            $this->tenant->id,
        );

        return ['username' => $this->username, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function semAtribuicao(): array
    {
        PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $this->operador->id)->delete();

        return ['username' => $this->username, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function perfilInativo(): array
    {
        PdvOperatorProfile::withoutGlobalScopes()->update(['status' => PdvOperatorProfile::STATUS_INACTIVE]);

        return ['username' => $this->username, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function semPerfil(): array
    {
        PdvOperatorProfile::withoutGlobalScopes()->delete();

        return ['username' => $this->username, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function atribuicaoRevogada(): array
    {
        PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->where('user_id', $this->operador->id)
            ->update(['status' => PdvOperatorBranchAssignment::STATUS_REVOKED, 'revoked_at' => now()]);

        return ['username' => $this->username, 'password' => self::SENHA];
    }
}
