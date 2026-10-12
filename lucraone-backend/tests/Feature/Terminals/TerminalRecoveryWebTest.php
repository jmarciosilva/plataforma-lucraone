<?php

namespace Tests\Feature\Terminals;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantResolver;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class TerminalRecoveryWebTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Terminal $terminal;

    private User $admin;

    private string $csrf = 'synthetic-csrf-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->active()->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->tenant);
        $company = Company::factory()->forCurrentTenant($this->tenant->id)->create();
        $branch = Branch::factory()->forCompany($company)->create();
        $this->terminal = Terminal::factory()->forBranch($branch)->create([
            'status' => Terminal::STATUS_ACTIVE,
            'installation_id' => (string) Str::uuid(),
        ]);
        $this->admin = User::factory()->forTenant($this->tenant)->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $this->admin);
    }

    public function test_admin_sees_recovery_action_for_active_installed_terminal(): void
    {
        $response = $this->actingAs($this->admin)->withSession($this->tenantSession())->get(route('terminals.show', $this->terminal));

        $response->assertOk()
            ->assertSee('gerar código de recuperação')
            ->assertSee('recuperar credencial do terminal')
            ->assertSee('armazenamento seguro indisponível');
    }

    public function test_web_recovery_returns_one_time_code_without_flash_or_identity_changes(): void
    {
        config(['session.driver' => 'cookie']);
        Log::spy();
        $identity = $this->terminal->only(['id', 'tenant_id', 'company_id', 'branch_id', 'installation_id']);

        $response = $this->actingAs($this->admin)->postJson(
            route('terminals.credential-recovery', $this->terminal),
            ['reason' => 'lost'],
            ['X-CSRF-TOKEN' => $this->csrf],
        );

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertJsonStructure(['data' => ['recovery_code', 'expires_at', 'terminal' => ['id', 'name']]]);

        $code = $response->json('data.recovery_code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}\.[A-Z0-9]{12}$/', $code);
        $response->assertJsonPath('data.recovery_code', $code);
        $this->assertSame($identity, $this->terminal->fresh()->only(array_keys($identity)));
        $this->assertDatabaseHas('terminal_credential_operations', ['terminal_id' => $this->terminal->id, 'kind' => 'RECOVERY', 'reason' => 'lost']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'credential.recovery.authorized', 'entity_id' => $this->terminal->id]);

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringNotContainsString($code, $cookie->getValue());
        }
        $persisted = json_encode([
            DB::table('terminal_credential_operations')->get(),
            DB::table('audit_logs')->get(),
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($code, $persisted);
        foreach (['info', 'debug', 'warning', 'error', 'critical'] as $method) {
            Log::shouldNotHaveReceived($method);
        }

        $this->actingAs($this->admin)->get(route('terminals.show', $this->terminal))
            ->assertOk()->assertDontSee($code);
    }

    public function test_recovery_route_uses_web_csrf_middleware_and_rejects_invalid_reason(): void
    {
        $route = Route::getRoutes()->getByName('terminals.credential-recovery');
        $this->assertContains('web', $route->gatherMiddleware());

        $this->asAdmin()->postJson(route('terminals.credential-recovery', $this->terminal), ['reason' => 'invented'], ['X-CSRF-TOKEN' => $this->csrf])
            ->assertUnprocessable();

        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public function test_blocked_or_pending_terminal_does_not_show_action_or_issue_code(): void
    {
        foreach ([Terminal::STATUS_BLOCKED, Terminal::STATUS_PENDING, Terminal::STATUS_REVOKED] as $status) {
            $this->terminal->forceFill(['status' => $status])->save();
            $this->actingAs($this->admin)->withSession($this->tenantSession())->get(route('terminals.show', $this->terminal))
                ->assertOk()->assertDontSee('gerar código de recuperação');
            $this->asAdmin()->postJson(route('terminals.credential-recovery', $this->terminal), ['reason' => 'lost'], ['X-CSRF-TOKEN' => $this->csrf])
                ->assertStatus(409);
        }

        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public function test_missing_installation_does_not_show_action_or_issue_code(): void
    {
        DB::table('terminals')->where('id', $this->terminal->id)->update(['installation_id' => null]);
        $this->actingAs($this->admin)->withSession($this->tenantSession())->get(route('terminals.show', $this->terminal))
            ->assertOk()->assertDontSee('gerar código de recuperação');
        $this->asAdmin()->postJson(route('terminals.credential-recovery', $this->terminal), ['reason' => 'lost'], ['X-CSRF-TOKEN' => $this->csrf])
            ->assertStatus(409);
        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public function test_unauthorized_user_and_foreign_tenant_cannot_issue_recovery(): void
    {
        $ordinary = User::factory()->forTenant($this->tenant)->create();
        $this->actingAs($ordinary)->withSession($this->tenantSession())->postJson(
            route('terminals.credential-recovery', $this->terminal), ['reason' => 'lost'], ['X-CSRF-TOKEN' => $this->csrf],
        )->assertForbidden();

        $otherTenant = Tenant::factory()->active()->create();
        $otherAdmin = User::factory()->forTenant($otherTenant)->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($otherTenant);
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($otherTenant, $otherAdmin);
        $this->actingAs($otherAdmin)->withSession([TenantResolver::SESSAO_TENANT => $otherTenant->id, '_token' => $this->csrf])
            ->postJson(route('terminals.credential-recovery', $this->terminal), ['reason' => 'lost'], ['X-CSRF-TOKEN' => $this->csrf])
            ->assertNotFound();

        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public function test_machine_credential_cannot_use_the_web_recovery_route(): void
    {
        $this->actingAs($this->terminal, 'sanctum')->postJson(
            route('terminals.credential-recovery', $this->terminal), ['reason' => 'lost'], ['X-CSRF-TOKEN' => $this->csrf],
        )->assertRedirect(route('login'));
        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public function test_administrative_rate_limit_stops_excess_recovery_emissions(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->asAdmin()->postJson(
                route('terminals.credential-recovery', $this->terminal),
                ['reason' => 'lost'],
                ['X-CSRF-TOKEN' => $this->csrf],
            )->assertOk();
        }

        $this->asAdmin()->postJson(
            route('terminals.credential-recovery', $this->terminal),
            ['reason' => 'lost'],
            ['X-CSRF-TOKEN' => $this->csrf],
        )->assertStatus(429);

        $this->assertDatabaseCount('terminal_credential_operations', 5);
    }

    private function tenantSession(): array
    {
        return [TenantResolver::SESSAO_TENANT => $this->tenant->id, '_token' => $this->csrf];
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession($this->tenantSession());
    }
}
