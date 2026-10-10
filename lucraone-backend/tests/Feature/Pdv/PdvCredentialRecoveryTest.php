<?php

namespace Tests\Feature\Pdv;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalCredentialOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('credential-lifecycle')]
class PdvCredentialRecoveryTest extends PdvTestCase
{
    private function admin(bool $authorized = true): string
    {
        $user = User::factory()->forTenant($this->terminal->tenant)->create();
        if ($authorized) {
            app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->terminal->tenant);
            app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->terminal->tenant, $user);
        }

        return $user->createToken('auth_token', ['business:read', 'business:write'])->plainTextToken;
    }

    private function authorizeCode(string $human)
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer '.$human, 'X-Tenant-ID' => $this->terminal->tenant_id])
            ->postJson('/api/v1/pdv/admin/terminals/'.$this->terminal->id.'/credential-recovery-codes', ['reason' => 'lost']);
    }

    private function recover(array $payload, string $ip = self::IP_CLIENTE)
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/pdv/terminal/credential/recover', $payload);
    }

    private function recoveryPayload(string $code): array
    {
        return ['recovery_code' => $code, 'installation_id' => $this->terminal->fresh()->installation_id,
            'delivery_secret' => bin2hex(random_bytes(32))];
    }

    public function test_authorized_recovery_revokes_old_at_authorization_and_preserves_identity(): void
    {
        $old = $this->credencialDeMaquina();
        $identity = $this->terminal->fresh()->only(['id', 'installation_id', 'tenant_id', 'company_id', 'branch_id']);
        $human = $this->admin();
        $issued = $this->authorizeCode($human)->assertOk()->assertHeader('Pragma', 'no-cache');
        $code = $issued->json('data.recovery_code');
        $this->flushHeaders();
        $this->getTerminal($old)->assertUnauthorized();
        $payload = $this->recoveryPayload($code);
        $first = $this->recover($payload)->assertOk();
        $candidate = $first->json('data.credential.access_token');
        $second = $this->recover($payload)->assertOk();
        $this->assertSame($candidate, $second->json('data.credential.access_token'));
        $this->assertDatabaseCount('personal_access_tokens', 2); // human + candidate
        $this->getTerminal($candidate)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($candidate)->postJson('/api/v1/pdv/terminal/credential/confirm', ['operation_id' => $first->json('data.rotation.id')])->assertOk();
        $this->getTerminal($candidate)->assertOk();
        $this->recover($payload)->assertUnprocessable();
        $this->assertSame($identity, $this->terminal->fresh()->only(array_keys($identity)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'credential.recovery.authorized']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'credential.recovery.consumed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'credential.rotation.confirmed']);
    }

    public function test_unauthorized_admin_and_other_tenant_cannot_issue_code(): void
    {
        $old = $this->credencialDeMaquina();
        $this->authorizeCode($this->admin(false))->assertForbidden();
        $other = User::factory()->forTenant(Tenant::factory()->create())->create();
        $this->authorizeCode($other->createToken('auth_token', ['business:write'])->plainTextToken)->assertForbidden();
        $this->flushHeaders();
        $this->getTerminal($old)->assertOk();
        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public function test_machine_and_read_only_human_cannot_authorize_recovery(): void
    {
        $old = $this->credencialDeMaquina();
        $this->authorizeCode($old)->assertForbidden();
        $human = User::factory()->forTenant($this->terminal->tenant)->create()->createToken('read', ['business:read'])->plainTextToken;
        $this->authorizeCode($human)->assertForbidden();
    }

    #[DataProvider('invalidTerminalStates')]
    public function test_ineligible_terminal_cannot_be_recovered(string $state): void
    {
        if ($state !== 'PENDING') {
            $this->credencialDeMaquina();
            $this->terminal->fresh()->update(['status' => $state]);
        }
        $this->authorizeCode($this->admin())->assertUnauthorized();
        $this->assertDatabaseCount('terminal_credential_operations', 0);
        $this->assertSame($state, $this->terminal->fresh()->status);
    }

    public static function invalidTerminalStates(): array
    {
        return [['PENDING'], ['BLOCKED'], ['REVOKED']];
    }

    #[DataProvider('failures')]
    public function test_public_failures_are_indistinguishable(string $failure): void
    {
        $this->credencialDeMaquina();
        $code = $this->authorizeCode($this->admin())->assertOk()->json('data.recovery_code');
        $payload = $this->recoveryPayload($code);
        match ($failure) {
            'unknown' => $payload['recovery_code'] = 'ZZZZZZ.ZZZZZZZZZZZZ',
            'wrong' => $payload['recovery_code'] = substr($code, 0, 7).'ZZZZZZZZZZZZ',
            'expired' => $this->travel(11)->minutes(),
            'installation' => $payload['installation_id'] = (string) Str::uuid(),
            'blocked' => $this->terminal->fresh()->update(['status' => 'BLOCKED']),
            'revoked' => $this->terminal->fresh()->update(['status' => 'REVOKED']),
            'company' => $this->terminal->company->update(['status' => 'INACTIVE']),
            'branch' => $this->terminal->branch->update(['status' => 'INACTIVE']),
            'tenant' => $this->terminal->tenant->update(['active' => false]),
        };
        $response = $this->recover($payload);
        $this->assertErroPdv($response, 422, 'recovery_failed');
        $this->assertSame('Não foi possível concluir a operação de credencial.', $response->json('error.message'));
    }

    public static function failures(): array
    {
        return array_map(fn ($value) => [$value], ['unknown', 'wrong', 'expired', 'installation', 'blocked', 'revoked', 'company', 'branch', 'tenant']);
    }

    public function test_bad_attempts_are_persistent_and_disable_code(): void
    {
        $this->credencialDeMaquina();
        $code = $this->authorizeCode($this->admin())->assertOk()->json('data.recovery_code');
        $wrong = $this->recoveryPayload(substr($code, 0, 7).'ZZZZZZZZZZZZ');
        for ($i = 0; $i < 5; $i++) {
            $this->recover($wrong, '198.51.100.'.(10 + $i))->assertUnprocessable();
        }
        $this->assertDatabaseHas('terminal_credential_operations', ['attempts' => 5, 'status' => 'INVALIDATED']);
        $this->recover($this->recoveryPayload($code))->assertUnprocessable();
    }

    public function test_consumed_code_cannot_create_another_candidate_with_different_secret(): void
    {
        $this->credencialDeMaquina();
        $code = $this->authorizeCode($this->admin())->assertOk()->json('data.recovery_code');
        $this->recover($this->recoveryPayload($code))->assertOk();
        $this->recover($this->recoveryPayload($code))->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_new_authorization_invalidates_previous_code_and_candidate(): void
    {
        $this->credencialDeMaquina();
        $human = $this->admin();
        $codeA = $this->authorizeCode($human)->assertOk()->json('data.recovery_code');
        $payload = $this->recoveryPayload($codeA);
        $candidate = $this->recover($payload)->assertOk()->json('data.credential.access_token');
        $this->authorizeCode($human)->assertOk();
        $this->recover($payload)->assertUnprocessable();
        $this->getTerminal($candidate)->assertUnauthorized();
    }

    public function test_response_loss_during_authorization_can_be_recovered_by_new_authorization(): void
    {
        $this->credencialDeMaquina();
        $human = $this->admin();
        $this->authorizeCode($human)->assertOk(); // discard response
        $code = $this->authorizeCode($human)->assertOk()->json('data.recovery_code');
        $this->recover($this->recoveryPayload($code))->assertOk();
        $this->assertDatabaseCount('terminal_credential_operations', 2);
    }

    public function test_storage_failure_does_not_consume_code_or_leave_token(): void
    {
        $this->credencialDeMaquina();
        $code = $this->authorizeCode($this->admin())->assertOk()->json('data.recovery_code');
        DB::unprepared("CREATE TRIGGER reject_candidate BEFORE INSERT ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->recover($this->recoveryPayload($code))->assertStatus(500);
        $this->assertDatabaseHas('terminal_credential_operations', ['status' => 'ISSUED', 'consumed_at' => null]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_no_recovery_secrets_in_database_audit_or_logs(): void
    {
        $this->credencialDeMaquina();
        $human = $this->admin();
        Log::spy();
        $code = $this->authorizeCode($human)->assertOk()->json('data.recovery_code');
        $payload = $this->recoveryPayload($code);
        $candidate = $this->recover($payload)->assertOk()->json('data.credential.access_token');
        $data = json_encode([DB::table('terminal_credential_operations')->get(), DB::table('audit_logs')->get(), DB::table('personal_access_tokens')->get()]);
        foreach ([$code, $payload['delivery_secret'], $candidate] as $secret) {
            $this->assertStringNotContainsString($secret, $data);
        }
        foreach (['info', 'debug', 'warning', 'error', 'critical'] as $method) {
            Log::shouldNotHaveReceived($method);
        }
    }

    public function test_cleanup_expires_pending_candidate_and_preserves_history(): void
    {
        $this->credencialDeMaquina();
        $code = $this->authorizeCode($this->admin())->assertOk()->json('data.recovery_code');
        $this->recover($this->recoveryPayload($code))->assertOk();
        $this->travel(11)->minutes();
        $this->artisan('pdv:expire-credential-operations')->assertSuccessful();
        $this->assertDatabaseHas('terminal_credential_operations', ['status' => 'EXPIRED']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_cleanup_processes_more_than_one_batch_without_skipping_terminals(): void
    {
        for ($i = 0; $i < 101; $i++) {
            $terminal = Terminal::factory()->forBranch($this->terminal->branch)->create();
            TerminalCredentialOperation::create([
                'id' => (string) Str::uuid(), 'terminal_id' => $terminal->id,
                'tenant_id' => $terminal->tenant_id, 'company_id' => $terminal->company_id,
                'branch_id' => $terminal->branch_id, 'installation_id' => (string) Str::uuid(),
                'kind' => 'RECOVERY', 'status' => 'ISSUED', 'expires_at' => now()->subMinute(),
            ]);
        }
        $this->artisan('pdv:expire-credential-operations')->assertSuccessful();
        $this->assertSame(101, TerminalCredentialOperation::where('status', 'EXPIRED')->count());
        $this->assertSame(0, TerminalCredentialOperation::where('status', 'ISSUED')->count());
    }

    public function test_rate_limit_stops_recovery_retries_and_has_retry_after(): void
    {
        $payload = ['recovery_code' => 'ZZZZZZ.ZZZZZZZZZZZZ', 'installation_id' => (string) Str::uuid(), 'delivery_secret' => bin2hex(random_bytes(32))];
        for ($i = 0; $i < 10; $i++) {
            $this->recover($payload, '198.51.100.'.(30 + $i))->assertUnprocessable();
        }
        $response = $this->recover($payload, '198.51.100.200');
        $this->assertErroPdv($response, 429, 'rate_limited');
        $response->assertHeader('Retry-After');
    }

    public function test_authorization_storage_failure_preserves_previous_credential(): void
    {
        $old = $this->credencialDeMaquina();
        $human = $this->admin();
        DB::unprepared("CREATE TRIGGER reject_recovery_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->authorizeCode($human)->assertStatus(500);
        $this->flushHeaders();
        $this->getTerminal($old)->assertOk();
        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public function test_structural_errors_do_not_echo_recovery_secret(): void
    {
        $secret = 'synthetic-secret-that-must-not-be-echoed';
        $response = $this->recover(['recovery_code' => $secret, 'delivery_secret' => $secret]);
        $this->assertErroPdv($response, 422, 'validation_error');
        $this->assertStringNotContainsString($secret, $response->getContent());
    }

    public function test_token_in_query_string_does_not_authenticate_renewal(): void
    {
        $old = $this->credencialDeMaquina();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/pdv/terminal/credential/rotations?access_token='.urlencode($old), [
            'operation_id' => (string) Str::uuid(), 'delivery_secret' => bin2hex(random_bytes(32)),
        ])->assertUnauthorized();
    }

    public function test_recovery_admin_rate_limit_is_scoped_and_sanitized(): void
    {
        $this->credencialDeMaquina();
        $human = $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->authorizeCode($human)->assertOk();
        }
        $this->assertErroPdv($this->authorizeCode($human), 429, 'rate_limited');
        $this->assertDatabaseCount('terminal_credential_operations', 5);
    }
}
