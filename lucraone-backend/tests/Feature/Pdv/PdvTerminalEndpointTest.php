<?php

namespace Tests\Feature\Pdv;

use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PDV-BE-05 — GET /api/v1/pdv/terminal.
 *
 * Pipeline sob teste, pelas rotas reais:
 * `auth:sanctum` → `terminal.context` → `machine.ability:pdv:terminal:read`.
 *
 * O ponto central é que o enforcement do PDV-BE-04 continua valendo DO LADO DE
 * FORA, pelo HTTP: bloquear um Terminal ou suspender o estabelecimento precisa
 * derrubar a requisição seguinte, e o cliente não pode descobrir qual das
 * condições falhou.
 */
class PdvTerminalEndpointTest extends PdvTestCase
{
    public function test_credencial_valida_devolve_o_proprio_terminal(): void
    {
        $token = $this->credencialDeMaquina();
        $terminal = $this->terminal->fresh();

        $this->getTerminal($token)
            ->assertOk()
            ->assertJsonPath('data.terminal.id', $terminal->id)
            ->assertJsonPath('data.terminal.name', 'Caixa 001')
            ->assertJsonPath('data.terminal.status', 'ACTIVE')
            ->assertJsonPath('data.terminal.installation_id', $terminal->installation_id)
            ->assertJsonPath('data.tenant.id', $terminal->tenant_id)
            ->assertJsonPath('data.tenant.name', $terminal->tenant->name)
            ->assertJsonPath('data.company.id', $terminal->company_id)
            ->assertJsonPath('data.company.trade_name', $terminal->company->trade_name)
            ->assertJsonPath('data.branch.id', $terminal->branch_id)
            ->assertJsonPath('data.branch.name', $terminal->branch->name)
            ->assertJsonPath('data.branch.code', $terminal->branch->code);
    }

    public function test_contrato_e_fechado_e_nao_devolve_credencial(): void
    {
        $token = $this->credencialDeMaquina();

        $resposta = $this->getTerminal($token)->assertOk();
        $dados = $resposta->json('data');

        $this->assertSame(['terminal', 'tenant', 'company', 'branch', 'credential'], array_keys($dados));
        $this->assertSame(['id', 'name', 'status', 'installation_id'], array_keys($dados['terminal']));
        $this->assertSame(['id', 'name'], array_keys($dados['tenant']));
        $this->assertSame(['id', 'trade_name'], array_keys($dados['company']));
        $this->assertSame(['id', 'name', 'code'], array_keys($dados['branch']));

        // Só o prazo. Nunca o token, nunca o hash.
        $this->assertSame(['expires_at'], array_keys($dados['credential']));
        $this->assertNotEmpty($dados['credential']['expires_at']);

        $corpo = $resposta->getContent();
        $this->assertStringNotContainsString($token, $corpo);
        foreach ([
            'access_token', 'token_type', 'code_hash', 'selector', 'attempts',
            'consumed_at', 'invalidated_at', 'abilities', 'tokenable',
            'permissions', 'memberships', 'document', 'legal_name', 'password',
        ] as $proibido) {
            $this->assertStringNotContainsString($proibido, $corpo);
        }
    }

    public function test_expires_at_vem_do_token_da_requisicao(): void
    {
        $token = $this->credencialDeMaquina();
        $esperado = $this->terminal->fresh()->tokens()->sole()->expires_at;

        $this->getTerminal($token)
            ->assertOk()
            ->assertJsonPath('data.credential.expires_at', $esperado->toIso8601String());
    }

    public function test_sem_token_responde_401_do_contrato_pdv(): void
    {
        $this->credencialDeMaquina();

        $this->assertErroPdv($this->getTerminal(), 401, 'unauthenticated');
    }

    #[DataProvider('tokensInvalidos')]
    public function test_token_invalido_responde_401(string $token): void
    {
        $this->credencialDeMaquina();

        $this->assertErroPdv($this->getTerminal($token), 401, 'unauthenticated');
    }

    public static function tokensInvalidos(): array
    {
        return [
            'lixo' => ['nao-e-um-token'],
            'formato plausivel' => ['1|'.str_repeat('a', 40)],
            'id inexistente' => ['999999|'.str_repeat('b', 40)],
        ];
    }

    public function test_token_expirado_responde_401_generico(): void
    {
        $token = $this->credencialDeMaquina();
        $this->getTerminal($token)->assertOk();

        $this->travel((int) config('pdv.machine_credentials.ttl_minutes') + 1)->minutes();

        $resposta = $this->getTerminal($token);
        $this->assertErroPdv($resposta, 401, 'unauthenticated');

        // Não diz "expirado": é a mesma resposta de token inexistente.
        $this->assertStringNotContainsStringIgnoringCase('expir', $resposta->getContent());
    }

    public function test_credencial_rotacionada_perde_acesso_e_a_nova_funciona(): void
    {
        $antiga = $this->credencialDeMaquina();
        $this->getTerminal($antiga)->assertOk();

        $nova = app(IssueTerminalMachineCredential::class)
            ->issue($this->terminal->fresh())->plainTextToken;

        $this->assertErroPdv($this->getTerminal($antiga), 401, 'unauthenticated');
        $this->getTerminal($nova)->assertOk();
    }

    /**
     * Estado operacional derruba a requisição seguinte.
     *
     * Em todos os casos a credencial foi emitida com tudo em ordem, um único
     * estado muda, e o MESMO token é reusado. Nenhuma resposta revela qual das
     * entidades saiu de operação — um token velho na mão de alguém não pode
     * virar sonda do estado do estabelecimento.
     */
    #[DataProvider('estadosQueDerrubam')]
    public function test_estado_nao_operacional_responde_401_generico(string $entidade, string $estado): void
    {
        $token = $this->credencialDeMaquina();
        $this->getTerminal($token)->assertOk();

        $terminal = $this->terminal->fresh();

        match ($entidade) {
            'terminal' => $terminal->update(['status' => $estado]),
            'tenant' => $terminal->tenant->update(
                $estado === 'DELETED'
                    ? ['status' => 'ACTIVE', 'active' => true]
                    : ['status' => $estado, 'active' => false]
            ),
            'company' => $terminal->company->update(['status' => $estado]),
            'branch' => $terminal->branch->update(['status' => $estado]),
        };

        if ($entidade === 'tenant' && $estado === 'DELETED') {
            $terminal->tenant->delete();
        }

        $resposta = $this->getTerminal($token);

        $this->assertErroPdv($resposta, 401, 'unauthenticated');
        $this->assertSame('Credencial ausente ou inválida.', $resposta->json('error.message'));

        // Nenhum vestígio de qual entidade ou estado reprovou.
        foreach (['BLOCKED', 'REVOKED', 'SUSPENDED', 'CANCELLED', 'INACTIVE', 'tenant', 'branch', 'company'] as $vazamento) {
            $this->assertStringNotContainsString($vazamento, $resposta->getContent());
        }
    }

    public static function estadosQueDerrubam(): array
    {
        return [
            'terminal bloqueado' => ['terminal', 'BLOCKED'],
            'terminal revogado' => ['terminal', 'REVOKED'],
            'tenant suspenso' => ['tenant', 'SUSPENDED'],
            'tenant cancelado' => ['tenant', 'CANCELLED'],
            'tenant soft-deleted' => ['tenant', 'DELETED'],
            'company inativa' => ['company', 'INACTIVE'],
            'company suspensa' => ['company', 'SUSPENDED'],
            'branch inativa' => ['branch', 'INACTIVE'],
            'branch suspensa' => ['branch', 'SUSPENDED'],
        ];
    }

    public function test_terminal_bloqueado_tem_o_token_removido_fisicamente(): void
    {
        $token = $this->credencialDeMaquina();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->terminal->fresh()->update(['status' => Terminal::STATUS_BLOCKED]);

        // Política do PDV-BE-04: sair de ACTIVE revoga.
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertErroPdv($this->getTerminal($token), 401, 'unauthenticated');
    }

    public function test_terminal_revogado_tem_o_token_removido_fisicamente(): void
    {
        $token = $this->credencialDeMaquina();

        $this->terminal->fresh()->update(['status' => Terminal::STATUS_REVOKED]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertErroPdv($this->getTerminal($token), 401, 'unauthenticated');
    }

    public function test_tenant_trial_ativo_continua_autenticando(): void
    {
        $this->terminal->tenant->update(['status' => 'TRIAL', 'active' => true]);
        $token = $this->credencialDeMaquina();

        $this->getTerminal($token)->assertOk();
    }

    public function test_token_humano_nao_entra_na_rota_de_maquina(): void
    {
        $this->credencialDeMaquina();
        $pessoa = $this->pessoaComVinculo();
        $token = $pessoa->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;

        $this->assertErroPdv($this->getTerminal($token), 403, 'forbidden');
    }

    public function test_token_humano_com_ability_de_maquina_continua_recusado(): void
    {
        $this->credencialDeMaquina();
        $pessoa = $this->pessoaComVinculo();

        // Se fosse a ability que barra, este caso passaria. É o tipo do sujeito
        // que barra, no terminal.context, antes da ability.
        $token = $pessoa->createToken('auth_token', [MachineTokenAbility::TERMINAL_READ])->plainTextToken;

        $this->assertErroPdv($this->getTerminal($token), 403, 'forbidden');
    }

    public function test_credencial_sem_a_ability_responde_403(): void
    {
        $terminal = $this->terminal;
        $terminal->update(['installation_id' => (string) Str::uuid(), 'status' => Terminal::STATUS_ACTIVE]);
        $token = $terminal->fresh()->createToken('pdv-machine', ['pdv:outra:coisa'])->plainTextToken;

        $this->assertErroPdv($this->getTerminal($token), 403, 'forbidden');
    }

    public function test_x_tenant_id_correto_e_recusado(): void
    {
        $token = $this->credencialDeMaquina();

        $resposta = $this->getTerminal($token, ['X-Tenant-ID' => $this->terminal->fresh()->tenant_id]);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_x_tenant_id_diferente_e_recusado(): void
    {
        $token = $this->credencialDeMaquina();
        $outro = Terminal::factory()->create();

        $resposta = $this->getTerminal($token, ['X-Tenant-ID' => $outro->tenant_id]);

        $this->assertErroPdv($resposta, 403, 'forbidden');
    }

    public function test_recusa_por_header_nao_deixa_contexto_pela_metade(): void
    {
        $token = $this->credencialDeMaquina();
        $outro = Terminal::factory()->create();

        $this->getTerminal($token, ['X-Tenant-ID' => $outro->tenant_id])->assertForbidden();

        $this->assertFalse(app(TerminalContext::class)->resolved());
        $this->assertFalse(app(TenantContext::class)->resolved());
    }

    public function test_rota_de_negocio_humana_continua_recusando_terminal(): void
    {
        $token = $this->credencialDeMaquina();

        // Regressão da fronteira do PDV-BE-04, agora com a credencial vinda do
        // caminho real. As rotas humanas mantêm o contrato antigo de erro.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/products')->assertForbidden();
    }

    private function pessoaComVinculo(): User
    {
        $terminal = $this->terminal->fresh();
        $pessoa = User::factory()->create(['status' => 'ACTIVE']);
        TenantUser::create([
            'tenant_id' => $terminal->tenant_id,
            'user_id' => $pessoa->id,
            'status' => TenantUser::STATUS_ACTIVE,
        ]);

        return $pessoa->fresh();
    }
}
