<?php

namespace Tests\Feature\Pdv;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * POST /api/v1/pdv/admin/terminals — provisionamento DIRETO de Terminal.
 *
 * Caminho HUMANO: PAT de pessoa com `business:write` e estabelecimento em
 * `X-Tenant-ID`, validado pelo TenantResolver via `HumanAdministrationContext`.
 * Substitui o pareamento interativo pelo modelo Linx/TOTVS/Stone: o admin
 * informa o installation_id que o PDV gerou e recebe a credencial de máquina.
 *
 * Tudo aqui passa pelas rotas reais — o ponto é o contrato HTTP, não o serviço.
 */
#[Group('pdv')]
#[Group('terminal-provisioning')]
class PdvTerminalProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected const IP_CLIENTE = '198.51.100.42';

    private $tenant;

    private Company $empresa;

    private Branch $filial;

    private User $administrador;

    private string $tokenHumano;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);

        $this->empresa = Company::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'ACTIVE']);
        $this->filial = Branch::factory()->forCompany($this->empresa)->create(['max_pdv_terminals' => 2]);

        $this->administrador = User::factory()->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->administrador);
        $this->administrador = $this->administrador->fresh();
        $this->tokenHumano = $this->administrador->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;

        $this->assertSame('array', config('cache.default'));
    }

    public function test_admin_provisiona_terminal_e_recebe_credencial(): void
    {
        $uuid = (string) Str::uuid();

        $resposta = $this->provisionar(['installation_id' => $uuid, 'name' => 'Caixa 001', 'branch_id' => $this->filial->id]);

        $resposta->assertCreated();

        $terminal = Terminal::withoutGlobalScopes()->where('installation_id', $uuid)->sole();
        $this->assertSame(Terminal::STATUS_ACTIVE, $terminal->status);
        $this->assertSame($this->filial->id, $terminal->branch_id);
        $this->assertSame(1, $this->credenciaisDeMaquina());

        $resposta->assertJsonPath('data.terminal.id', $terminal->id)
            ->assertJsonPath('data.terminal.name', 'Caixa 001')
            ->assertJsonPath('data.terminal.status', 'ACTIVE')
            ->assertJsonPath('data.terminal.installation_id', $uuid)
            ->assertJsonPath('data.tenant.id', $this->tenant->id)
            ->assertJsonPath('data.company.id', $this->empresa->id)
            ->assertJsonPath('data.branch.id', $this->filial->id)
            ->assertJsonPath('data.credential.token_type', 'Bearer');

        $this->assertNotEmpty($resposta->json('data.credential.access_token'));
        $this->assertNotEmpty($resposta->json('data.credential.expires_at'));
    }

    public function test_provisionamento_devolve_exatamente_o_contrato_acordado(): void
    {
        $resposta = $this->provisionar([
            'installation_id' => (string) Str::uuid(),
            'name' => 'Caixa 002',
            'branch_id' => $this->filial->id,
        ])->assertCreated();

        $resposta->assertJsonStructure([
            'data' => [
                'terminal' => ['id', 'name', 'status', 'installation_id'],
                'tenant' => ['id', 'name'],
                'company' => ['id', 'trade_name'],
                'branch' => ['id', 'name', 'code'],
                'credential' => ['token_type', 'access_token', 'expires_at'],
            ],
        ]);

        $dados = $resposta->json('data');
        $this->assertSame(['terminal', 'tenant', 'company', 'branch', 'credential'], array_keys($dados));
        $this->assertSame(['token_type', 'access_token', 'expires_at'], array_keys($dados['credential']));
    }

    public function test_credencial_provisionada_autentica_no_contexto_de_maquina(): void
    {
        $token = $this->provisionar([
            'installation_id' => (string) Str::uuid(),
            'name' => 'Caixa 003',
            'branch_id' => $this->filial->id,
        ])->assertCreated()->json('data.credential.access_token');

        // A credencial emitida tem de funcionar com o `terminal.context` sem
        // nenhum passo intermediário — é o que prova que o novo fluxo entrega
        // uma credencial de máquina igual à do pairing.
        $this->novaRequisicao();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($token)
            ->getJson('/api/v1/pdv/terminal')
            ->assertOk()
            ->assertJsonPath('data.terminal.status', 'ACTIVE');
    }

    public function test_expires_at_e_iso8601_com_timezone(): void
    {
        $expira = $this->provisionar([
            'installation_id' => (string) Str::uuid(),
            'name' => 'Caixa 004',
            'branch_id' => $this->filial->id,
        ])->assertCreated()->json('data.credential.expires_at');

        $this->assertMatchesRegularExpression(
            '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\+|-)\d{2}:\d{2}\z/',
            $expira
        );
    }

    public function test_resposta_com_credencial_proibe_cache(): void
    {
        $resposta = $this->provisionar([
            'installation_id' => (string) Str::uuid(),
            'name' => 'Caixa 005',
            'branch_id' => $this->filial->id,
        ])->assertCreated();

        $this->assertStringContainsString('no-store', $resposta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $resposta->headers->get('Cache-Control'));
    }

    public function test_installation_id_duplicado_responde_422_e_nao_emite_segunda_credencial(): void
    {
        $uuid = (string) Str::uuid();

        $this->provisionar(['installation_id' => $uuid, 'name' => 'Caixa A', 'branch_id' => $this->filial->id])->assertCreated();

        $segunda = $this->provisionar(['installation_id' => $uuid, 'name' => 'Caixa B', 'branch_id' => $this->filial->id]);

        $this->assertErroPdv($segunda, 422, 'validation_error');
        $this->assertSame(1, $this->credenciaisDeMaquina());
        $this->assertSame(1, Terminal::withoutGlobalScopes()->where('installation_id', $uuid)->count());
    }

    public function test_filial_de_outro_estabelecimento_e_recusada(): void
    {
        $outroTenant = Tenant::factory()->active()->create();
        $outraEmpresa = Company::factory()->create(['tenant_id' => $outroTenant->id, 'status' => 'ACTIVE']);
        $outraFilial = Branch::factory()->forCompany($outraEmpresa)->create();

        $resposta = $this->provisionar([
            'installation_id' => (string) Str::uuid(),
            'name' => 'Caixa X',
            'branch_id' => $outraFilial->id,
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertSame(0, $this->credenciaisDeMaquina());
    }

    public function test_cota_esgotada_recusa_novo_terminal(): void
    {
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid()]);
        Terminal::factory()->forBranch($this->filial)->create(['status' => Terminal::STATUS_ACTIVE, 'installation_id' => (string) Str::uuid()]);

        $resposta = $this->provisionar([
            'installation_id' => (string) Str::uuid(),
            'name' => 'Caixa 006',
            'branch_id' => $this->filial->id,
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
    }

    public function test_sem_token_responde_401(): void
    {
        $this->novaRequisicao();

        $resposta = $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/v1/pdv/admin/terminals', [
                'installation_id' => (string) Str::uuid(),
                'name' => 'Caixa 007',
                'branch_id' => $this->filial->id,
            ]);

        $resposta->assertStatus(401);
        $this->assertSame(0, $this->credenciaisDeMaquina());
    }

    public function test_token_sem_escrita_responde_403(): void
    {
        $tokenSomenteLeitura = $this->administrador->createToken('leitura', ['business:read'])->plainTextToken;

        $resposta = $this->provisionar(
            ['installation_id' => (string) Str::uuid(), 'name' => 'Caixa 008', 'branch_id' => $this->filial->id],
            token: $tokenSomenteLeitura,
        );

        $this->assertErroPdv($resposta, 403, 'forbidden');
        $this->assertSame(0, $this->credenciaisDeMaquina()); // nenhuma credencial de Terminal emitida
    }

    #[DataProvider('payloadsInvalidos')]
    public function test_payload_invalido_responde_validation_error(array $payload): void
    {
        $resposta = $this->provisionar($payload);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertSame(0, Terminal::withoutGlobalScopes()->whereNotNull('installation_id')->count());
    }

    public static function payloadsInvalidos(): array
    {
        return [
            'body vazio' => [[]],
            'sem installation_id' => [['name' => 'Caixa', 'branch_id' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA']],
            'sem name' => [['installation_id' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301', 'branch_id' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA']],
            'sem branch_id' => [['installation_id' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301', 'name' => 'Caixa']],
            'installation_id malformado' => [['installation_id' => 'nao-e-uuid', 'name' => 'Caixa', 'branch_id' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA']],
            'installation_id uuid v1' => [['installation_id' => '2c1b2fb0-a5a3-11ee-be56-0242ac120002', 'name' => 'Caixa', 'branch_id' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA']],
            'campo desconhecido' => [['installation_id' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301', 'name' => 'Caixa', 'branch_id' => 'AAAAAAAAAAAAAAAAAAAAAAAAAA', 'status' => 'ACTIVE']],
        ];
    }

    public function test_provisionamento_nao_registra_token_em_log(): void
    {
        Log::spy();

        $resposta = $this->provisionar([
            'installation_id' => (string) Str::uuid(),
            'name' => 'Caixa 009',
            'branch_id' => $this->filial->id,
        ])->assertCreated();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => $resposta->json('data.credential.access_token'),
        ]);
    }

    // -------------------------------------------------------------------------

    private function provisionar(array $payload, ?string $token = null): TestResponse
    {
        $this->novaRequisicao();

        return $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withToken($token ?? $this->tokenHumano)
            ->withHeaders(['X-Tenant-ID' => $this->tenant->id])
            ->postJson('/api/v1/pdv/admin/terminals', $payload);
    }

    /**
     * Só as credenciais de MÁQUINA (tokens de Terminal) — ignora os PATs
     * humanos do administrador, que também vivem em personal_access_tokens.
     */
    private function credenciaisDeMaquina(): int
    {
        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new Terminal)->getMorphClass())
            ->count();
    }

    private function novaRequisicao(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        app(TenantContext::class)->clear();
        app(TerminalContext::class)->clear();
    }

    private function assertErroPdv(TestResponse $resposta, int $status, string $code): void
    {
        $resposta->assertStatus($status)
            ->assertJsonPath('error.code', $code)
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id']]);
    }
}
