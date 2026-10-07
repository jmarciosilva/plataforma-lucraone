<?php

namespace Tests\Feature\Automation;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Automation\Application\Actions\SendEmailAction;
use App\Modules\Automation\Application\RuleEngine;
use App\Modules\Automation\Domain\EmailRecipients;
use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Automation\Infrastructure\Mail\AutomationAlertMail;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SEC-05 — abuso no envio de e-mail das automações.
 *
 * Automação é notificação operacional, não campanha. Os defaults precisam ser
 * volume baixo, destinatários controlados e rastro auditável.
 *
 * Antes desta rodada a única barreira era `max:1000` caracteres em
 * `action_config.recipients` — que cabe **143** endereços mínimos — e não havia
 * limite de execuções.
 */
class SendEmailAbuseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private Company $company;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->outroTenant = Tenant::factory()->active()->create();
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $this->conceder($this->admin, ['manage-automations', 'view-automations']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------

    private function conceder(User $usuario, array $permissoes): void
    {
        $papel = Role::factory()->forTenant($this->tenant->id)->create(['id' => (string) Str::ulid()]);

        foreach ($permissoes as $nome) {
            $papel->grantPermission(
                Permission::factory()->forTenant($this->tenant->id)->create([
                    'name' => $nome,
                    'description' => $nome,
                ])
            );
        }

        $usuario->assignRole($papel, $this->tenant->id);
    }

    private function requisicao()
    {
        return $this->withToken($this->token)->withHeader('X-Tenant-ID', $this->tenant->id);
    }

    /** Cria a regra pela API, que é onde a configuração é validada. */
    private function criarPelaApi(string $recipients)
    {
        return $this->requisicao()->postJson('/api/v1/automation-rules', [
            'name' => 'avisar time',
            'trigger' => TriggerCatalog::PRODUTO_CRIADO,
            'action' => SendEmailAction::CHAVE,
            'action_config' => [
                'recipients' => $recipients,
                'subject' => 'aviso',
                'message' => 'confira.',
            ],
        ]);
    }

    /**
     * Grava a regra direto pelo factory, sem passar pela validação — é o que
     * simula configuração legada, persistida antes desta política.
     */
    private function regraLegada(string $recipients, ?Tenant $tenant = null): AutomationRule
    {
        return AutomationRule::factory()
            ->forTenant(($tenant ?? $this->tenant)->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->action(SendEmailAction::CHAVE, [
                'recipients' => $recipients,
                'subject' => 'aviso',
                'message' => 'confira.',
            ])
            ->create();
    }

    /** Executa a regra pelo motor, que é quem chama a ação. */
    private function executar(AutomationRule $regra): AutomationLog
    {
        $logs = app(RuleEngine::class)->processar(
            $regra->tenant_id,
            TriggerCatalog::PRODUTO_CRIADO,
            ['nome' => 'Produto', 'sku' => 'X-1', 'status' => 'active', 'company_id' => $this->company->id]
        );

        return collect($logs)->firstWhere('automation_rule_id', $regra->id);
    }

    private function enderecos(int $quantidade): string
    {
        return collect(range(1, $quantidade))
            ->map(fn (int $i) => "pessoa{$i}@casa.test")
            ->implode(', ');
    }

    // ---------------------------------------------------------------
    // Validação da configuração — API
    // ---------------------------------------------------------------

    public function test_um_destinatario_valido_e_aceito(): void
    {
        $this->criarPelaApi('compras@casa.test')->assertCreated();
    }

    public function test_varios_destinatarios_validos_sao_aceitos(): void
    {
        $this->criarPelaApi('a@casa.test, b@casa.test, c@casa.test')->assertCreated();
    }

    public function test_aceita_virgula_ponto_e_virgula_e_quebra_de_linha(): void
    {
        $this->criarPelaApi("a@casa.test, b@casa.test; c@casa.test\nd@casa.test")->assertCreated();
    }

    public function test_espacos_em_excesso_nao_invalidam(): void
    {
        $this->criarPelaApi("   a@casa.test ,,  b@casa.test  \n\n ")->assertCreated();
    }

    public function test_destinatario_invalido_recusa_a_configuracao(): void
    {
        $this->criarPelaApi('a@casa.test, isso-nao-e-email')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action_config.recipients']);

        $this->assertDatabaseCount('automation_rules', 0);
    }

    public function test_lista_vazia_recusa_a_configuracao(): void
    {
        $this->criarPelaApi('   ,  ; ')->assertStatus(422);
        $this->assertDatabaseCount('automation_rules', 0);
    }

    public function test_exatamente_no_maximo_e_aceito(): void
    {
        $this->criarPelaApi($this->enderecos(EmailRecipients::MAXIMO))->assertCreated();
    }

    public function test_acima_do_maximo_e_recusado(): void
    {
        $this->criarPelaApi($this->enderecos(EmailRecipients::MAXIMO + 1))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action_config.recipients']);

        $this->assertDatabaseCount('automation_rules', 0);
    }

    public function test_duplicados_nao_contam_para_o_maximo(): void
    {
        // Dez endereços distintos mais repetições, inclusive trocando a caixa:
        // depois de normalizar continuam dez.
        $lista = $this->enderecos(EmailRecipients::MAXIMO)
            .', PESSOA1@CASA.TEST, pessoa2@casa.test';

        $this->criarPelaApi($lista)->assertCreated();
    }

    public function test_mesma_politica_vale_no_painel_web(): void
    {
        // API e painel compartilham AutomationRuleRequest, então a política não
        // pode divergir entre os dois caminhos.
        $this->actingAs($this->admin)
            ->withSession(['tenant_ativo' => $this->tenant->id])
            ->post(route('automations.store'), [
                'name' => 'avisar time',
                'trigger' => TriggerCatalog::PRODUTO_CRIADO,
                'action' => SendEmailAction::CHAVE,
                'action_config' => [
                    'recipients' => $this->enderecos(EmailRecipients::MAXIMO + 1),
                    'subject' => 'aviso',
                ],
            ])
            ->assertSessionHasErrors('action_config.recipients');

        $this->assertDatabaseCount('automation_rules', 0);
    }

    // ---------------------------------------------------------------
    // Normalização no envio
    // ---------------------------------------------------------------

    public function test_envio_normaliza_caixa_e_remove_duplicados(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('Compras@Casa.TEST, compras@casa.test, GERENCIA@casa.test');

        $log = $this->executar($regra);

        $this->assertSame(AutomationLog::EXECUTADO, $log->result);

        Mail::assertQueued(AutomationAlertMail::class, function (AutomationAlertMail $mail) {
            return $mail->hasTo('compras@casa.test') && $mail->hasTo('gerencia@casa.test');
        });
        Mail::assertQueuedCount(1);
        $this->assertSame(2, $log->outcome['recipient_count']);
    }

    // ---------------------------------------------------------------
    // Configuração legada — defesa em profundidade
    // ---------------------------------------------------------------

    public function test_config_legada_acima_do_maximo_nao_envia(): void
    {
        Mail::fake();

        $regra = $this->regraLegada($this->enderecos(EmailRecipients::MAXIMO + 5));

        $log = $this->executar($regra);

        Mail::assertNothingQueued();
        $this->assertSame(AutomationLog::FALHOU, $log->result);
        $this->assertSame(EmailRecipients::MOTIVO_EXCEDE_MAXIMO, $log->message);
    }

    public function test_config_legada_com_endereco_invalido_nao_envia(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('a@casa.test, isso-nao-e-email');

        $log = $this->executar($regra);

        Mail::assertNothingQueued();
        $this->assertSame(AutomationLog::FALHOU, $log->result);
        $this->assertSame(EmailRecipients::MOTIVO_INVALIDOS, $log->message);
    }

    // ---------------------------------------------------------------
    // Limite de envio
    // ---------------------------------------------------------------

    public function test_abaixo_do_limite_envia(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('compras@casa.test');

        $this->assertSame(AutomationLog::EXECUTADO, $this->executar($regra)->result);
        $this->assertSame(AutomationLog::EXECUTADO, $this->executar($regra)->result);

        Mail::assertQueuedCount(2);
    }

    public function test_ultima_execucao_dentro_do_limite_ainda_envia(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('compras@casa.test');

        // Carrega o contador até uma execução antes do teto.
        RateLimiter::increment(
            SendEmailAction::chaveDaRegra($regra),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::EXECUCOES_POR_REGRA - 1
        );

        $this->assertSame(AutomationLog::EXECUTADO, $this->executar($regra)->result);
        Mail::assertQueuedCount(1);
    }

    public function test_acima_do_limite_por_regra_bloqueia_sem_enviar(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('compras@casa.test');

        RateLimiter::increment(
            SendEmailAction::chaveDaRegra($regra),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::EXECUCOES_POR_REGRA
        );

        $log = $this->executar($regra);

        Mail::assertNothingQueued();
        $this->assertSame(AutomationLog::FALHOU, $log->result);
        $this->assertSame(SendEmailAction::MOTIVO_LIMITE, $log->message);
    }

    public function test_limite_de_entregas_por_tenant_bloqueia_sem_enviar(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('a@casa.test, b@casa.test');

        RateLimiter::increment(
            SendEmailAction::chaveDoTenant($regra->tenant_id),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::ENTREGAS_POR_TENANT
        );

        $log = $this->executar($regra);

        Mail::assertNothingQueued();
        $this->assertSame(AutomationLog::FALHOU, $log->result);
        $this->assertSame(SendEmailAction::MOTIVO_LIMITE, $log->message);
    }

    public function test_entregas_sao_contadas_pelo_numero_de_destinatarios(): void
    {
        Mail::fake();

        $regra = $this->regraLegada($this->enderecos(5));
        $this->executar($regra);

        $this->assertSame(
            5,
            RateLimiter::attempts(SendEmailAction::chaveDoTenant($regra->tenant_id)),
            'o orçamento do estabelecimento conta entregas, não execuções'
        );
    }

    public function test_bloqueio_expira_com_a_janela(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('compras@casa.test');

        RateLimiter::increment(
            SendEmailAction::chaveDaRegra($regra),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::EXECUCOES_POR_REGRA
        );

        $this->assertSame(AutomationLog::FALHOU, $this->executar($regra)->result);

        $this->travel(SendEmailAction::JANELA_SEGUNDOS + 60)->seconds();

        $this->assertSame(AutomationLog::EXECUTADO, $this->executar($regra)->result);
        Mail::assertQueuedCount(1);
    }

    // ---------------------------------------------------------------
    // Isolamento entre estabelecimentos e entre regras
    // ---------------------------------------------------------------

    public function test_limite_de_um_estabelecimento_nao_atinge_o_outro(): void
    {
        Mail::fake();

        $daCasa = $this->regraLegada('compras@casa.test');
        $doVizinho = $this->regraLegada('compras@vizinho.test', $this->outroTenant);

        RateLimiter::increment(
            SendEmailAction::chaveDoTenant($this->tenant->id),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::ENTREGAS_POR_TENANT
        );

        $this->assertSame(AutomationLog::FALHOU, $this->executar($daCasa)->result);
        $this->assertSame(AutomationLog::EXECUTADO, $this->executar($doVizinho)->result);

        Mail::assertQueuedCount(1);
    }

    public function test_limite_de_uma_regra_nao_atinge_outra_regra(): void
    {
        Mail::fake();

        $primeira = $this->regraLegada('a@casa.test');
        $segunda = $this->regraLegada('b@casa.test');

        RateLimiter::increment(
            SendEmailAction::chaveDaRegra($primeira),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::EXECUCOES_POR_REGRA
        );

        $this->assertSame(AutomationLog::FALHOU, $this->executar($primeira)->result);
        $this->assertSame(AutomationLog::EXECUTADO, $this->executar($segunda)->result);
    }

    public function test_chaves_do_limiter_incluem_o_estabelecimento(): void
    {
        $daCasa = $this->regraLegada('a@casa.test');
        $doVizinho = $this->regraLegada('b@vizinho.test', $this->outroTenant);

        $this->assertStringContainsString($this->tenant->id, SendEmailAction::chaveDaRegra($daCasa));
        $this->assertStringContainsString($this->outroTenant->id, SendEmailAction::chaveDaRegra($doVizinho));
        $this->assertNotSame(
            SendEmailAction::chaveDaRegra($daCasa),
            SendEmailAction::chaveDaRegra($doVizinho)
        );
    }

    // ---------------------------------------------------------------
    // Auditoria
    // ---------------------------------------------------------------

    public function test_sucesso_registra_contagem_e_nao_a_lista_de_enderecos(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('compras@casa.test, gerencia@casa.test');

        $log = $this->executar($regra);

        $this->assertSame(AutomationLog::EXECUTADO, $log->result);
        $this->assertSame(2, $log->outcome['recipient_count']);

        // A lista completa de endereços não entra no registro.
        $registro = json_encode($log->outcome);
        $this->assertStringNotContainsString('compras@casa.test', $registro);
        $this->assertStringNotContainsString('gerencia@casa.test', $registro);
    }

    public function test_bloqueio_fica_registrado_e_consultavel(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('compras@casa.test');

        RateLimiter::increment(
            SendEmailAction::chaveDaRegra($regra),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::EXECUCOES_POR_REGRA
        );

        $this->executar($regra);

        $this->assertDatabaseHas('automation_logs', [
            'tenant_id' => $this->tenant->id,
            'automation_rule_id' => $regra->id,
            'action' => SendEmailAction::CHAVE,
            'result' => AutomationLog::FALHOU,
            'message' => SendEmailAction::MOTIVO_LIMITE,
        ]);
    }

    public function test_bloqueio_nao_propaga_excecao_para_a_fila(): void
    {
        Mail::fake();

        $regra = $this->regraLegada('compras@casa.test');

        RateLimiter::increment(
            SendEmailAction::chaveDaRegra($regra),
            SendEmailAction::JANELA_SEGUNDOS,
            SendEmailAction::EXECUCOES_POR_REGRA
        );

        // O gatilho real roda pelo listener enfileirado. Se o bloqueio
        // escapasse como exceção, a fila tentaria de novo — três vezes — e cada
        // tentativa reexecutaria todas as regras do gatilho.
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
        ]);

        Mail::assertNothingQueued();
        $this->assertSame(
            1,
            AutomationLog::withoutGlobalScopes()->where('automation_rule_id', $regra->id)->count(),
            'o bloqueio deve gerar um registro só, não um por tentativa da fila'
        );
    }
}
