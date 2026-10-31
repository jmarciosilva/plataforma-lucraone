<?php

namespace Tests\Feature\Admin;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Application\TerminalProvisioningService;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Suíte de unidade do TerminalProvisioningService — o modelo de provisionamento
 * DIRETO (Linx/TOTVS/Stone). Cobre a criação ACTIVE com installation_id, a
 * emissão de credencial na mesma transação, a unicidade da instalação, o
 * pertencimento da filial e a cota.
 */
#[Group('terminal')]
#[Group('terminal-provisioning')]
class TerminalProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Company $empresa;

    private Branch $filial;

    private User $administrador;

    private TerminalProvisioningService $servico;

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

        $this->administrador = User::factory()->create();
        $this->servico = app(TerminalProvisioningService::class);

        // AuditLog::logAction exige TenantContext resolvido — em produção o
        // HumanAdministrationContext/TenantResolver o fixa antes do controller.
        app(TenantContext::class)->set($this->tenant->id);
    }

    public function test_provisiona_terminal_active_com_instalacao_e_credencial(): void
    {
        $uuid = (string) Str::uuid();

        $resultado = $this->servico->provision(
            $this->administrador,
            $this->tenant->id,
            $uuid,
            'PDV 01',
            $this->filial->id,
        );

        $terminal = Terminal::withoutGlobalScopes()->findOrFail($resultado->terminalId);

        $this->assertSame(Terminal::STATUS_ACTIVE, $terminal->status);
        $this->assertSame($uuid, $terminal->installation_id);
        $this->assertSame($this->filial->id, $terminal->branch_id);
        $this->assertSame($this->empresa->id, $terminal->company_id);
        $this->assertSame($this->tenant->id, $terminal->tenant_id);

        // Credencial emitida na mesma travessia, com texto puro só aqui.
        $this->assertNotEmpty($resultado->credential);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'terminal.provisioned',
            'entity_id' => $terminal->id,
        ]);
    }

    public function test_normaliza_installation_id_para_minusculas(): void
    {
        $uuid = strtoupper((string) Str::uuid());

        $resultado = $this->servico->provision(
            $this->administrador,
            $this->tenant->id,
            $uuid,
            'PDV 01',
            $this->filial->id,
        );

        $this->assertSame(strtolower($uuid), $resultado->installationId);
    }

    public function test_recusa_instalacao_ja_cadastrada(): void
    {
        $uuid = (string) Str::uuid();
        Terminal::factory()->forBranch($this->filial)->create([
            'installation_id' => $uuid,
            'status' => Terminal::STATUS_ACTIVE,
        ]);

        try {
            $this->servico->provision($this->administrador, $this->tenant->id, $uuid, 'PDV Dup', $this->filial->id);
            $this->fail('Esperava ValidationException para instalação duplicada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('installation_id', $e->errors());
        }

        // Nada novo persistido: nem Terminal, nem credencial.
        $this->assertSame(1, Terminal::withoutGlobalScopes()->where('installation_id', $uuid)->count());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_recusa_filial_de_outro_estabelecimento(): void
    {
        $outroTenant = Tenant::factory()->active()->create();
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outroTenant->id, 'status' => 'ACTIVE']);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create();

        $this->expectException(ValidationException::class);

        $this->servico->provision(
            $this->administrador,
            $this->tenant->id,
            (string) Str::uuid(),
            'PDV 01',
            $outraFilial->id,
        );
    }

    public function test_recusa_quando_cota_esgotada(): void
    {
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid()]);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_BLOCKED, 'installation_id' => (string) Str::uuid()]);

        $this->expectException(ValidationException::class);

        $this->servico->provision(
            $this->administrador,
            $this->tenant->id,
            (string) Str::uuid(),
            'PDV 03',
            $this->filial->id,
        );
    }

    public function test_instalacao_malformada_e_recusada(): void
    {
        $this->expectException(ValidationException::class);

        $this->servico->provision(
            $this->administrador,
            $this->tenant->id,
            'nao-e-uuid',
            'PDV 01',
            $this->filial->id,
        );
    }
}
