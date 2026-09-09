<?php

namespace Tests\Feature\Automation;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Automation\Domain\Models\AutomationLog;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Reporting\Infrastructure\Mail\SalesSummaryMail;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F2.5 — tarefas agendadas (cron)
 *
 * Cobre o envio periódico de relatório que a F2.4 deixou pendente e a poda do
 * histórico de automações.
 */
class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create(['name' => 'Casa Alta', 'timezone' => 'UTC']);
        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();
    }

    public function test_resumo_de_vendas_vai_para_quem_pode_ver_relatorios(): void
    {
        Mail::fake();

        $comPermissao = User::factory()->forTenant($this->tenant)->create(['email' => 'gerente@casa.test']);
        $this->conceder($comPermissao, 'view-reports');

        // Sem permissão de relatórios: não deve receber
        User::factory()->forTenant($this->tenant)->create(['email' => 'operador@casa.test']);

        Order::factory()
            ->forCompany($this->company)
            ->status(Order::STATUS_COMPLETED)
            ->create(['total' => 500, 'subtotal' => 500]);

        $this->artisan('relatorios:enviar-resumo', ['--dias' => 7])
            ->assertSuccessful();

        Mail::assertQueued(SalesSummaryMail::class, function (SalesSummaryMail $mail) {
            return $mail->hasTo('gerente@casa.test')
                && ! $mail->hasTo('operador@casa.test')
                && (float) $mail->totais['faturamento'] === 500.0;
        });
    }

    public function test_resumo_pula_estabelecimento_sem_destinatario(): void
    {
        Mail::fake();

        User::factory()->forTenant($this->tenant)->create();

        $this->artisan('relatorios:enviar-resumo')
            ->expectsOutputToContain('ninguém com permissão de relatórios')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_poda_remove_execucoes_antigas_e_preserva_falhas_recentes(): void
    {
        $regra = AutomationRule::factory()->forTenant($this->tenant->id)->create();

        $antiga = AutomationLog::factory()->forRule($regra)->create(['ran_at' => now()->subDays(60)]);
        $recente = AutomationLog::factory()->forRule($regra)->create(['ran_at' => now()->subDays(5)]);
        $falhaRecente = AutomationLog::factory()->forRule($regra)->failed()->create(['ran_at' => now()->subDays(60)]);
        $falhaAntiga = AutomationLog::factory()->forRule($regra)->failed()->create(['ran_at' => now()->subDays(200)]);

        $this->artisan('automacoes:limpar-logs', ['--dias' => 30, '--dias-falhas' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('automation_logs', ['id' => $antiga->id]);
        $this->assertDatabaseHas('automation_logs', ['id' => $recente->id]);

        // Falha de 60 dias sobrevive: falhas ficam mais tempo que execuções normais
        $this->assertDatabaseHas('automation_logs', ['id' => $falhaRecente->id]);
        $this->assertDatabaseMissing('automation_logs', ['id' => $falhaAntiga->id]);
    }

    public function test_tarefas_estao_agendadas(): void
    {
        $agendadas = collect(app(Schedule::class)->events())
            ->map(fn ($evento) => $evento->command)
            ->implode(' ');

        $this->assertStringContainsString('relatorios:enviar-resumo', $agendadas);
        $this->assertStringContainsString('automacoes:limpar-logs', $agendadas);
    }

    private function conceder(User $user, string $permissao): void
    {
        $papel = Role::factory()
            ->admin()
            ->forTenant($this->tenant->id)
            ->create(['id' => (string) Str::ulid()]);

        $papel->grantPermission(
            Permission::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'name' => $permissao],
                ['id' => (string) Str::ulid(), 'description' => $permissao]
            )
        );

        $user->assignRole($papel, $this->tenant->id);
    }
}
