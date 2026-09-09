<?php

namespace Tests\Feature\Admin;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Automation\Application\Actions\CreateNotificationAction;
use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Automation\Domain\Operator;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F2.5 — Advanced Automation (painel administrativo)
 */
class AutomationWebManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAtual;

    private Tenant $outroTenant;

    private Company $company;

    private User $admin;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAtual = Tenant::factory()->active()->create(['name' => 'Casa Alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['name' => 'Mercado Norte']);
        $this->company = Company::factory()->forCurrentTenant($this->tenantAtual->id)->active()->create();
        $this->admin = User::factory()->forTenant($this->tenantAtual)->create(['name' => 'Ana Admin']);
        $this->adminRole = Role::factory()
            ->admin()
            ->forTenant($this->tenantAtual->id)
            ->create(['id' => (string) Str::ulid()]);

        $this->tornarAdmin($this->admin, $this->tenantAtual);
    }

    public function test_listagem_isola_por_tenant_e_mostra_ajuda(): void
    {
        AutomationRule::factory()->forTenant($this->tenantAtual->id)->create(['name' => 'regra daqui']);
        AutomationRule::factory()->forTenant($this->outroTenant->id)->create(['name' => 'regra de fora']);

        $this->actingAs($this->admin)
            ->get(route('automations.index'))
            ->assertOk()
            ->assertSee('automações')
            ->assertSee('ajuda de automações')
            ->assertSee('regra daqui')
            ->assertDontSee('regra de fora');
    }

    public function test_criar_regra_pela_tela(): void
    {
        $resposta = $this->actingAs($this->admin)->post(route('automations.store'), [
            'name' => 'avisar produto novo',
            'description' => 'para conferir cadastro',
            'trigger' => TriggerCatalog::PRODUTO_CRIADO,
            'conditions' => [
                ['campo' => 'nome', 'operador' => Operator::CONTEM, 'valor' => 'vinho'],
            ],
            'action' => CreateNotificationAction::CHAVE,
            'action_config' => [
                'title' => 'produto novo: {nome}',
                'message' => 'confira o cadastro.',
                'level' => Notification::NIVEL_INFO,
            ],
            'active' => '1',
        ]);

        $regra = AutomationRule::withoutGlobalScopes()->where('name', 'avisar produto novo')->firstOrFail();

        $resposta->assertRedirect(route('automations.show', $regra));
        $this->assertSame($this->tenantAtual->id, $regra->tenant_id);
        $this->assertSame('vinho', $regra->conditions[0]['valor']);
        $this->assertTrue($regra->active);
    }

    public function test_condicao_invalida_volta_com_erro(): void
    {
        $this->actingAs($this->admin)
            ->post(route('automations.store'), [
                'name' => 'regra torta',
                'trigger' => TriggerCatalog::PRODUTO_CRIADO,
                'conditions' => [
                    ['campo' => 'quantidade', 'operador' => Operator::IGUAL, 'valor' => '5'],
                ],
                'action' => CreateNotificationAction::CHAVE,
                'action_config' => [
                    'title' => 't', 'message' => 'm', 'level' => Notification::NIVEL_INFO,
                ],
            ])
            ->assertSessionHasErrors('conditions.0.campo');

        $this->assertSame(0, AutomationRule::withoutGlobalScopes()->count());
    }

    public function test_linha_de_condicao_em_branco_e_descartada(): void
    {
        $this->actingAs($this->admin)->post(route('automations.store'), [
            'name' => 'sem condicao',
            'trigger' => TriggerCatalog::PRODUTO_CRIADO,
            // O formulário sempre manda uma linha; vazia não é erro
            'conditions' => [
                ['campo' => '', 'operador' => '', 'valor' => ''],
            ],
            'action' => CreateNotificationAction::CHAVE,
            'action_config' => [
                'title' => 't', 'message' => 'm', 'level' => Notification::NIVEL_INFO,
            ],
        ])->assertSessionHasNoErrors();

        $regra = AutomationRule::withoutGlobalScopes()->firstOrFail();
        $this->assertSame([], $regra->conditions);
    }

    public function test_ativar_e_desativar_pela_tela(): void
    {
        $regra = AutomationRule::factory()->forTenant($this->tenantAtual->id)->create(['active' => true]);

        $this->actingAs($this->admin)->post(route('automations.toggle', $regra));
        $this->assertFalse($regra->fresh()->active);

        $this->actingAs($this->admin)->post(route('automations.toggle', $regra));
        $this->assertTrue($regra->fresh()->active);
    }

    public function test_detalhe_mostra_a_regra_em_frase_e_o_historico(): void
    {
        $regra = AutomationRule::factory()
            ->forTenant($this->tenantAtual->id)
            ->trigger(TriggerCatalog::ESTOQUE_BAIXO)
            ->conditions([
                ['campo' => 'quantidade', 'operador' => Operator::MENOR_IGUAL, 'valor' => '5'],
            ])
            ->create(['name' => 'regra detalhada']);

        AutomationLog::factory()->forRule($regra)->failed('destinatário inválido')->create();

        $this->actingAs($this->admin)
            ->get(route('automations.show', $regra))
            ->assertOk()
            ->assertSee('regra detalhada')
            ->assertSee('estoque baixo')
            ->assertSee('quantidade em mãos')
            ->assertSee('é menor ou igual a')
            ->assertSee('destinatário inválido');
    }

    public function test_historico_geral_filtra_por_resultado(): void
    {
        $regra = AutomationRule::factory()->forTenant($this->tenantAtual->id)->create();
        AutomationLog::factory()->forRule($regra)->failed('quebrou')->create();
        AutomationLog::factory()->forRule($regra)->create(['message' => 'tudo certo']);

        $this->actingAs($this->admin)
            ->get(route('automations.logs', ['result' => AutomationLog::FALHOU]))
            ->assertOk()
            ->assertSee('quebrou')
            ->assertDontSee('tudo certo');
    }

    public function test_avisos_aparecem_e_podem_ser_marcados_como_lidos(): void
    {
        $regra = AutomationRule::factory()
            ->forTenant($this->tenantAtual->id)
            ->trigger(TriggerCatalog::PRODUTO_CRIADO)
            ->action(CreateNotificationAction::CHAVE, [
                'title' => 'produto novo: {nome}',
                'message' => 'confira.',
                'level' => Notification::NIVEL_ATENCAO,
            ])
            ->create();

        Product::factory()->create([
            'tenant_id' => $this->tenantAtual->id,
            'company_id' => $this->company->id,
            'name' => 'Vinho Verde',
        ]);

        $aviso = Notification::withoutGlobalScopes()->firstOrFail();

        // HasUlid guarda um objeto Ulid em memória; do banco volta string
        $this->assertSame((string) $regra->id, $aviso->automation_rule_id);

        $this->actingAs($this->admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('produto novo: Vinho Verde');

        $this->actingAs($this->admin)->post(route('notifications.read', $aviso));
        $this->assertNotNull($aviso->fresh()->read_at);
    }

    public function test_marcar_todos_como_lidos(): void
    {
        Notification::factory()->forTenant($this->tenantAtual->id)->count(3)->create();

        $this->actingAs($this->admin)->post(route('notifications.read-all'));

        $this->assertSame(
            0,
            Notification::withoutGlobalScopes()->whereNull('read_at')->count()
        );
    }

    public function test_avisos_isolam_por_tenant(): void
    {
        Notification::factory()->forTenant($this->tenantAtual->id)->create(['title' => 'aviso daqui']);
        Notification::factory()->forTenant($this->outroTenant->id)->create(['title' => 'aviso de fora']);

        $this->actingAs($this->admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('aviso daqui')
            ->assertDontSee('aviso de fora');
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $usuario = User::factory()->forTenant($this->tenantAtual)->create();

        $this->actingAs($usuario)->get(route('automations.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('automations.create'))->assertForbidden();
        $this->actingAs($usuario)->get(route('automations.logs'))->assertForbidden();

        // Avisos não exigem permissão: são operacionais
        $this->actingAs($usuario)->get(route('notifications.index'))->assertOk();
    }

    private function tornarAdmin(User $user, Tenant $tenant): void
    {
        foreach (['manage-automations', 'view-automations'] as $nome) {
            $this->adminRole->grantPermission(
                Permission::factory()->forTenant($tenant->id)->create([
                    'name' => $nome,
                    'description' => $nome,
                ])
            );
        }

        $user->assignRole($this->adminRole, $tenant->id);
    }
}
