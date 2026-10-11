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
            ->assertJsonPath('data.context.terminal.id', $this->terminal->id)
            ->assertJsonPath('data.context.branch.id', $this->branch->id)
            ->assertJsonStructure(['data' => [
                'session' => ['id', 'status', 'authenticated_at', 'last_revalidated_at', 'expires_at'],
                'credential' => ['token_type', 'access_token', 'expires_at'],
                'operator' => ['id', 'name', 'email'],
                'context' => ['terminal', 'tenant', 'company', 'branch'],
                'capabilities',
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

    public function test_prazo_absoluto_e_de_oito_horas_limitado_pela_credencial_de_maquina(): void
    {
        $this->login()->assertCreated();

        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();
        $maquina = $this->terminal->tokens()->sole();

        $this->assertSame(480, (int) config('pdv.operator_sessions.ttl_minutes'));
        $this->assertTrue($sessao->expires_at->lessThanOrEqualTo(now()->addMinutes(480)));

        // Nunca além da credencial que a abriu.
        $limiteDaMaquina = $maquina->created_at->copy()->addMinutes((int) config('sanctum.expiration'));
        $this->assertTrue($sessao->expires_at->lessThanOrEqualTo($limiteDaMaquina));
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
            'usuário inexistente' => ['usuarioInexistente'],
            'conta inativa' => ['contaInativa'],
            'membership suspensa' => ['membershipSuspensa'],
            'sem permissão access-pdv' => ['semPermissao'],
            'sem atribuição de filial' => ['semAtribuicao'],
            'atribuição revogada' => ['atribuicaoRevogada'],
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

    public function test_tentativa_recusada_e_auditada_sem_email_nem_senha(): void
    {
        $this->login(payload: ['email' => $this->operador->email, 'password' => 'errada-mas-longa'])
            ->assertStatus(401);

        $registro = AuditLog::withoutGlobalScopes()
            ->where('action', OperatorSessionAudit::LOGIN_FAILED)
            ->sole();

        // A pessoa é identificada quando a conta existe — a auditoria humana
        // não pode atribuir a ação à máquina.
        $this->assertSame($this->operador->id, $registro->user_id);
        $this->assertSame($this->tenant->id, $registro->tenant_id);
        $this->assertSame('credentials', $registro->changes['reason']);

        $serializado = json_encode($registro->toArray());
        $this->assertStringNotContainsString($this->operador->email, $serializado);
        $this->assertStringNotContainsString('errada-mas-longa', $serializado);
        $this->assertStringNotContainsString(self::SENHA, $serializado);
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
        $this->login(payload: ['email' => $this->operador->email, 'password' => 'outra-senha-x'])->assertStatus(401);

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
            'email' => $this->operador->email,
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
        $payload = ['email' => $this->operador->email, 'password' => 'errada-mas-longa'];

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
            $this->login(payload: ['email' => $this->operador->email, 'password' => 'errada-mas-longa'])
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
        $payload = ['email' => $this->operador->email, 'password' => 'errada-mas-longa'];
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
        $resposta = $this->login(payload: ['email' => 'nao-e-email', 'password' => '1']);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertStringNotContainsString('nao-e-email', $resposta->getContent());
    }

    // ----------------------------------------------------------------
    // Cenários de recusa. Cada um remove UMA peça do login legítimo.
    // ----------------------------------------------------------------

    /** @return array<string, string> */
    private function senhaInvalida(): array
    {
        return ['email' => $this->operador->email, 'password' => 'senha-que-nao-confere'];
    }

    /** @return array<string, string> */
    private function usuarioInexistente(): array
    {
        return ['email' => 'ninguem@exemplo.test', 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function contaInativa(): array
    {
        $this->operador->update(['status' => User::STATUS_INACTIVE]);

        return ['email' => $this->operador->email, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function membershipSuspensa(): array
    {
        $this->operador->joinTenant($this->tenant->id, TenantUser::STATUS_SUSPENDED);

        return ['email' => $this->operador->email, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function semPermissao(): array
    {
        $this->operador->removeRole(
            Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'pdv-operator')->sole(),
            $this->tenant->id,
        );

        return ['email' => $this->operador->email, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function semAtribuicao(): array
    {
        PdvOperatorBranchAssignment::withoutGlobalScopes()->where('user_id', $this->operador->id)->delete();

        return ['email' => $this->operador->email, 'password' => self::SENHA];
    }

    /** @return array<string, string> */
    private function atribuicaoRevogada(): array
    {
        PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->where('user_id', $this->operador->id)
            ->update(['status' => PdvOperatorBranchAssignment::STATUS_REVOKED, 'revoked_at' => now()]);

        return ['email' => $this->operador->email, 'password' => self::SENHA];
    }
}
