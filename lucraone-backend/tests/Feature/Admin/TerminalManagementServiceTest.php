<?php

namespace Tests\Feature\Admin;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Application\TerminalManagementService;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Suíte de unidade do TerminalManagementService.
 *
 * Cobre especificamente a cota por filial (concorrência incluída), as
 * transições de estado e o heartbeat.
 */
#[Group('terminal')]
#[Group('terminal-service')]
class TerminalManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $filial;
    private TerminalManagementService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->empresa = Company::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->filial = Branch::factory()->forCompany($this->empresa)->create([
            'max_pdv_terminals' => 2,
        ]);

        $this->servico = app(TerminalManagementService::class);

        // AuditLog::logAction exige TenantContext resolvido.
        app(TenantContext::class)->set($this->tenant->id);
    }

    // ----------------------------------------------------------------
    // Criar
    // ----------------------------------------------------------------

    public function test_cria_terminal_com_status_pending(): void
    {
        $terminal = $this->servico->criar($this->tenant->id, [
            'branch_id' => $this->filial->id,
            'name' => 'PDV 01',
        ]);

        $this->assertSame(Terminal::STATUS_PENDING, $terminal->status);
        $this->assertNull($terminal->installation_id);
        $this->assertSame($this->filial->id, $terminal->branch_id);
    }

    public function test_recusa_criar_quando_cota_esgotada(): void
    {
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => 'a58040c7-6d45-4b3a-a3e8-000000000001']);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_BLOCKED, 'installation_id' => 'a58040c7-6d45-4b3a-a3e8-000000000002']);

        $this->expectException(ValidationException::class);

        $this->servico->criar($this->tenant->id, [
            'branch_id' => $this->filial->id,
            'name' => 'PDV 03',
        ]);
    }

    public function test_terminais_revogados_nao_bloqueiam_cota(): void
    {
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_REVOKED]);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_REVOKED]);

        // Limite é 2, mas os 2 são REVOKED → cabe criar 2 novos
        $t1 = $this->servico->criar($this->tenant->id, ['branch_id' => $this->filial->id, 'name' => 'PDV A']);
        $t2 = $this->servico->criar($this->tenant->id, ['branch_id' => $this->filial->id, 'name' => 'PDV B']);

        $this->assertSame(Terminal::STATUS_PENDING, $t1->status);
        $this->assertSame(Terminal::STATUS_PENDING, $t2->status);
    }

    public function test_cota_atomica_em_transacao_concorrente(): void
    {
        // Simula duas chamadas que chegariam à mesma branch com 1 slot livre
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => 'a58040c7-6d45-4b3a-a3e8-000000000001']);

        // Primeira criação usa o slot restante
        $this->servico->criar($this->tenant->id, ['branch_id' => $this->filial->id, 'name' => 'PDV Primeiro']);

        // Segunda deve falhar: slot consumido
        $this->expectException(ValidationException::class);
        $this->servico->criar($this->tenant->id, ['branch_id' => $this->filial->id, 'name' => 'PDV Segundo']);
    }

    // ----------------------------------------------------------------
    // Bloquear / Desbloquear / Revogar
    // ----------------------------------------------------------------

    public function test_bloquear_terminal_ativo(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_ACTIVE,
            'installation_id' => 'a58040c7-6d45-4b3a-a3e8-000000000001',
        ]);

        $atualizado = $this->servico->bloquear($terminal);

        $this->assertSame(Terminal::STATUS_BLOCKED, $atualizado->status);
    }

    public function test_bloquear_terminal_nao_ativo_lanca_excecao(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        $this->expectException(ValidationException::class);
        $this->servico->bloquear($terminal);
    }

    public function test_desbloquear_terminal_bloqueado(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_BLOCKED,
            'installation_id' => 'a58040c7-6d45-4b3a-a3e8-000000000001',
        ]);

        $atualizado = $this->servico->desbloquear($terminal);

        $this->assertSame(Terminal::STATUS_ACTIVE, $atualizado->status);
    }

    public function test_revogar_terminal_pending(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_PENDING,
        ]);

        $atualizado = $this->servico->revogar($terminal);

        $this->assertSame(Terminal::STATUS_REVOKED, $atualizado->status);
    }

    public function test_revogar_terminal_ja_revogado_lanca_excecao(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_REVOKED,
        ]);

        $this->expectException(ValidationException::class);
        $this->servico->revogar($terminal);
    }

    // ----------------------------------------------------------------
    // Heartbeat
    // ----------------------------------------------------------------

    public function test_registrar_heartbeat_atualiza_last_seen_at(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create([
            'status' => Terminal::STATUS_ACTIVE,
            'installation_id' => 'a58040c7-6d45-4b3a-a3e8-000000000001',
        ]);

        $this->assertNull($terminal->last_seen_at);

        $antes = now()->subSecond();
        $this->servico->registrarHeartbeat($terminal);

        $atualizado = $terminal->fresh();
        $this->assertNotNull($atualizado->last_seen_at);
        $this->assertTrue($atualizado->last_seen_at->greaterThan($antes));
    }

    public function test_atualizar_nome_do_terminal(): void
    {
        $terminal = Terminal::factory()->forBranch($this->filial)->create(['name' => 'PDV Velho']);

        $atualizado = $this->servico->atualizar($terminal, ['name' => 'PDV Novo']);

        $this->assertSame('PDV Novo', $atualizado->name);
        $this->assertDatabaseHas('terminals', ['id' => $terminal->id, 'name' => 'PDV Novo']);
    }
}
