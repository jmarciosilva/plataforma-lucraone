<?php

namespace Tests\Feature\Automation;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Automation\Application\Actions\CreateNotificationAction;
use App\Modules\Automation\Application\Actions\UpdatePriceAction;
use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Automation\Domain\Operator;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F2.5 — Advanced Automation (API)
 */
class AutomationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->outroTenant = Tenant::factory()->active()->create();
        $this->admin = User::factory()->forTenant($this->tenant)->create();

        $this->conceder($this->admin, ['manage-automations', 'view-automations']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    public function test_cria_regra_com_condicao_valida(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/automation-rules', [
                'name' => 'avisar estoque baixo do arroz',
                'trigger' => TriggerCatalog::ESTOQUE_BAIXO,
                'conditions' => [
                    ['campo' => 'quantidade', 'operador' => Operator::MENOR_IGUAL, 'valor' => '5'],
                ],
                'action' => CreateNotificationAction::CHAVE,
                'action_config' => [
                    'title' => 'estoque baixo',
                    'message' => 'restam {quantidade}.',
                    'level' => Notification::NIVEL_ATENCAO,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.trigger', TriggerCatalog::ESTOQUE_BAIXO)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.conditions.0.campo', 'quantidade');

        $this->assertDatabaseHas('automation_rules', [
            'tenant_id' => $this->tenant->id,
            'name' => 'avisar estoque baixo do arroz',
        ]);
    }

    public function test_campo_fora_do_catalogo_e_recusado(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/automation-rules', [
                'name' => 'regra maliciosa',
                'trigger' => TriggerCatalog::ESTOQUE_BAIXO,
                'conditions' => [
                    ['campo' => 'tenant_id', 'operador' => Operator::IGUAL, 'valor' => 'x'],
                ],
                'action' => CreateNotificationAction::CHAVE,
                'action_config' => [
                    'title' => 't', 'message' => 'm', 'level' => Notification::NIVEL_INFO,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.campo']);
    }

    public function test_operador_incompativel_com_o_tipo_do_campo_e_recusado(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/automation-rules', [
                'name' => 'regra torta',
                'trigger' => TriggerCatalog::ESTOQUE_BAIXO,
                // `quantidade` é número: "contém" não vale
                'conditions' => [
                    ['campo' => 'quantidade', 'operador' => Operator::CONTEM, 'valor' => '5'],
                ],
                'action' => CreateNotificationAction::CHAVE,
                'action_config' => [
                    'title' => 't', 'message' => 'm', 'level' => Notification::NIVEL_INFO,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operador']);
    }

    public function test_campo_numerico_exige_valor_numerico(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/automation-rules', [
                'name' => 'regra com valor errado',
                'trigger' => TriggerCatalog::ESTOQUE_BAIXO,
                'conditions' => [
                    ['campo' => 'quantidade', 'operador' => Operator::MENOR, 'valor' => 'muito pouco'],
                ],
                'action' => CreateNotificationAction::CHAVE,
                'action_config' => [
                    'title' => 't', 'message' => 'm', 'level' => Notification::NIVEL_INFO,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.valor']);
    }

    public function test_acao_de_preco_exige_gatilho_com_produto(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/automation-rules', [
                'name' => 'reprecificar no pedido',
                // pedido concluído não carrega produto
                'trigger' => TriggerCatalog::PEDIDO_CONCLUIDO,
                'action' => UpdatePriceAction::CHAVE,
                'action_config' => [
                    'price_type' => Price::TYPE_SALE,
                    'operation' => UpdatePriceAction::OPERACAO_PERCENTUAL,
                    'amount' => 5,
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }

    public function test_configuracao_da_acao_e_exigida(): void
    {
        $this->requisicao()
            ->postJson('/api/v1/automation-rules', [
                'name' => 'sem configuração',
                'trigger' => TriggerCatalog::PRODUTO_CRIADO,
                'action' => CreateNotificationAction::CHAVE,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action_config.title', 'action_config.message']);
    }

    public function test_atualiza_e_desativa_regra(): void
    {
        $regra = AutomationRule::factory()->forTenant($this->tenant->id)->create(['name' => 'antiga']);

        $this->requisicao()
            ->putJson("/api/v1/automation-rules/{$regra->id}", [
                'name' => 'renomeada',
                'trigger' => TriggerCatalog::PRODUTO_CRIADO,
                'action' => CreateNotificationAction::CHAVE,
                'action_config' => [
                    'title' => 't', 'message' => 'm', 'level' => Notification::NIVEL_INFO,
                ],
                'active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'renomeada')
            ->assertJsonPath('data.active', false);
    }

    public function test_remove_regra(): void
    {
        $regra = AutomationRule::factory()->forTenant($this->tenant->id)->create();

        $this->requisicao()
            ->deleteJson("/api/v1/automation-rules/{$regra->id}")
            ->assertOk();

        $this->assertDatabaseMissing('automation_rules', ['id' => $regra->id]);
    }

    public function test_regras_isoladas_por_tenant(): void
    {
        $minha = AutomationRule::factory()->forTenant($this->tenant->id)->create();
        AutomationRule::factory()->forTenant($this->outroTenant->id)->create();

        $ids = collect(
            $this->requisicao()->getJson('/api/v1/automation-rules')->assertOk()->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($minha->id));
        $this->assertCount(1, $ids);
    }

    public function test_regra_de_outro_tenant_da_404(): void
    {
        $outra = AutomationRule::factory()->forTenant($this->outroTenant->id)->create();

        $this->requisicao()
            ->getJson("/api/v1/automation-rules/{$outra->id}")
            ->assertNotFound();
    }

    public function test_logs_listam_execucoes(): void
    {
        $regra = AutomationRule::factory()->forTenant($this->tenant->id)->create();
        AutomationLog::factory()->forRule($regra)->failed('deu ruim')->create();

        $this->requisicao()
            ->getJson('/api/v1/automation-logs?result='.AutomationLog::FALHOU)
            ->assertOk()
            ->assertJsonPath('data.0.result', AutomationLog::FALHOU)
            ->assertJsonPath('data.0.message', 'deu ruim');
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/v1/automation-rules')->assertUnauthorized();
        $this->getJson('/api/v1/automation-logs')->assertUnauthorized();
    }

    public function test_quem_so_visualiza_nao_pode_criar(): void
    {
        $observador = User::factory()->forTenant($this->tenant)->create();
        $this->conceder($observador, ['view-automations']);
        $token = $observador->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/automation-rules')
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->postJson('/api/v1/automation-rules', [
                'name' => 'tentativa',
                'trigger' => TriggerCatalog::PRODUTO_CRIADO,
                'action' => CreateNotificationAction::CHAVE,
                'action_config' => [
                    'title' => 't', 'message' => 'm', 'level' => Notification::NIVEL_INFO,
                ],
            ])
            ->assertForbidden();
    }

    private function requisicao()
    {
        return $this->withToken($this->token)->withHeader('X-Tenant-ID', $this->tenant->id);
    }

    private function conceder(User $user, array $permissoes): void
    {
        $papel = Role::factory()
            ->admin()
            ->forTenant($this->tenant->id)
            ->create(['id' => (string) Str::ulid(), 'name' => 'papel '.Str::random(5)]);

        // Permissão é única por tenant: dois papéis compartilham a mesma linha
        foreach ($permissoes as $nome) {
            $papel->grantPermission(
                Permission::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $this->tenant->id, 'name' => $nome],
                    ['id' => (string) Str::ulid(), 'description' => $nome]
                )
            );
        }

        $user->assignRole($papel, $this->tenant->id);
    }
}
