<?php

namespace Tests\Feature\Pdv;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('credential-lifecycle')]
class PdvCredentialRotationTest extends PdvTestCase
{
    private function payload(): array
    {
        return ['operation_id' => (string) Str::uuid(), 'delivery_secret' => bin2hex(random_bytes(32))];
    }

    private function lifecyclePost(string $path, string $token, array $payload = [])
    {
        $this->app['auth']->forgetGuards();
        app(TenantContext::class)->clear();
        app(TerminalContext::class)->clear();

        return $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/v1/pdv/'.$path, $payload);
    }

    public function test_rotation_retries_same_candidate_and_confirms_without_changing_identity(): void
    {
        $old = $this->credencialDeMaquina();
        $identity = $this->terminal->fresh()->only(['id', 'installation_id', 'tenant_id', 'company_id', 'branch_id']);
        $payload = $this->payload();
        $first = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk();
        $candidate = $first->json('data.credential.access_token');
        $first->assertJsonPath('data.rotation.status', 'PENDING')->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $second = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk();
        $this->assertSame($candidate, $second->json('data.credential.access_token'));
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->getTerminal($old)->assertOk();
        $this->getTerminal($candidate)->assertForbidden();
        $confirmed = $this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => $payload['operation_id']])->assertOk();
        $confirmed->assertJsonPath('data.rotation.status', 'CONFIRMED');
        $repeat = $this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => $payload['operation_id']])->assertOk();
        $this->assertSame($confirmed->json('data.credential.expires_at'), $repeat->json('data.credential.expires_at'));
        $this->assertNull($confirmed->json('data.credential.access_token'));
        $this->getTerminal($old)->assertUnauthorized();
        $this->getTerminal($candidate)->assertOk();
        $this->assertSame($identity, $this->terminal->fresh()->only(array_keys($identity)));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_conflicting_operation_or_changed_delivery_secret_does_not_replace_candidate(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk();
        $this->assertErroPdv($this->lifecyclePost('terminal/credential/rotations', $old, $this->payload()), 409, 'credential_conflict');
        $payload['delivery_secret'] = bin2hex(random_bytes(32));
        $this->assertErroPdv($this->lifecyclePost('terminal/credential/rotations', $old, $payload), 409, 'credential_conflict');
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_expired_pending_rotation_cannot_confirm_and_old_token_can_start_again(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $candidate = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk()->json('data.credential.access_token');
        $this->travel(11)->minutes();
        $this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => $payload['operation_id']])->assertUnauthorized();
        $this->lifecyclePost('terminal/credential/rotations', $old, $this->payload())->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->getTerminal($old)->assertOk();
    }

    public function test_candidate_can_confirm_after_old_token_expires(): void
    {
        $old = $this->credencialDeMaquina();
        $this->travel(719)->minutes();
        $payload = $this->payload();
        $candidate = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk()->json('data.credential.access_token');
        $this->travel(2)->minutes();
        $this->getTerminal($old)->assertUnauthorized();
        $this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => $payload['operation_id']])->assertOk();
        $this->getTerminal($candidate)->assertOk();
    }

    public function test_effective_expiration_respects_smaller_global_sanctum_limit(): void
    {
        config(['sanctum.expiration' => 30]);
        $old = $this->credencialDeMaquina();
        $current = $this->getTerminal($old)->assertOk();
        $this->assertSame(now()->addMinutes(30)->timestamp, strtotime($current->json('data.credential.expires_at')));
        $payload = $this->payload();
        $candidate = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk()->json('data.credential.access_token');
        $confirmed = $this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => $payload['operation_id']])->assertOk();
        $this->assertSame(now()->addMinutes(30)->timestamp, strtotime($confirmed->json('data.credential.expires_at')));
    }

    #[DataProvider('invalidSubjects')]
    public function test_invalid_machine_cannot_prepare(string $condition): void
    {
        $old = $this->credencialDeMaquina();
        match ($condition) {
            'invalid' => $old = '1|invalid',
            'expired' => $this->travel(721)->minutes(),
            'revoked' => $this->terminal->fresh()->update(['status' => 'REVOKED']),
            'blocked' => $this->terminal->fresh()->update(['status' => 'BLOCKED']),
            'tenant' => $this->terminal->tenant->update(['active' => false]),
            'company' => $this->terminal->company->update(['status' => 'INACTIVE']),
            'branch' => $this->terminal->branch->update(['status' => 'INACTIVE']),
        };
        $this->assertErroPdv($this->lifecyclePost('terminal/credential/rotations', $old, $this->payload()), 401, 'unauthenticated');
        $this->assertDatabaseCount('terminal_credential_operations', 0);
    }

    public static function invalidSubjects(): array
    {
        return array_map(fn ($value) => [$value], ['invalid', 'expired', 'revoked', 'blocked', 'tenant', 'company', 'branch']);
    }

    public function test_human_and_insufficient_ability_are_refused(): void
    {
        $human = User::factory()->create()->createToken('auth_token', ['pdv:credential:renew'])->plainTextToken;
        $this->lifecyclePost('terminal/credential/rotations', $human, $this->payload())->assertForbidden();
        $this->credencialDeMaquina();
        $insufficient = $this->terminal->fresh()->createToken('unrelated', ['pdv:terminal:read'], now()->addHour())->plainTextToken;
        $this->lifecyclePost('terminal/credential/rotations', $insufficient, $this->payload())->assertForbidden();
    }

    public function test_legacy_read_only_machine_has_narrow_transition_without_mutating_old_abilities(): void
    {
        $this->credencialDeMaquina();
        $legacy = $this->terminal->fresh()->createToken('pdv-machine', ['pdv:terminal:read'], now()->addHour());
        $this->lifecyclePost('terminal/credential/rotations', $legacy->plainTextToken, $this->payload())->assertOk();
        $this->assertSame(['pdv:terminal:read'], $legacy->accessToken->fresh()->abilities);
    }

    public function test_context_and_unknown_fields_are_refused(): void
    {
        $old = $this->credencialDeMaquina();
        $this->lifecyclePost('terminal/credential/rotations', $old, $this->payload() + ['tenant_id' => 'other'])->assertUnprocessable();
        $this->withHeaders(['X-Tenant-ID' => $this->terminal->tenant_id]);
        $this->lifecyclePost('terminal/credential/rotations', $old, $this->payload())->assertForbidden();
    }

    public function test_revocation_invalidates_pending_and_does_not_touch_human(): void
    {
        $human = User::factory()->create()->createToken('auth_token', ['business:read']);
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $candidate = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk()->json('data.credential.access_token');
        $this->terminal->fresh()->update(['status' => 'BLOCKED']);
        $this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => $payload['operation_id']])->assertUnauthorized();
        $this->assertNotNull($human->accessToken->fresh());
        $this->assertDatabaseHas('terminal_credential_operations', ['id' => $payload['operation_id'], 'status' => 'INVALIDATED']);
    }

    public function test_database_failure_rolls_back_candidate_and_preserves_old(): void
    {
        $old = $this->credencialDeMaquina();
        DB::unprepared("CREATE TRIGGER reject_rotation BEFORE INSERT ON terminal_credential_operations BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->lifecyclePost('terminal/credential/rotations', $old, $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->getTerminal($old)->assertOk();
    }

    public function test_no_secret_in_database_or_logs_and_request_id_is_preserved(): void
    {
        Log::spy();
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $this->withHeaders(['X-Request-ID' => 'rotation-synthetic']);
        $response = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk()->assertHeader('X-Request-ID', 'rotation-synthetic');
        $database = json_encode(DB::table('terminal_credential_operations')->get());
        $this->assertStringNotContainsString($payload['delivery_secret'], $database);
        $this->assertStringNotContainsString($response->json('data.credential.access_token'), $database);
        foreach (['info', 'debug', 'warning', 'error', 'critical'] as $method) {
            Log::shouldNotHaveReceived($method);
        }
    }

    /**
     * O isolamento entre terminais não depende da ability.
     *
     * Antes da Fase 5 a credencial emitida no pairing tinha apenas
     * `pdv:terminal:read`, então o outro terminal parava no middleware de
     * ability e recebia 403. Agora a emissão inicial já traz
     * `pdv:credential:confirm`, e a tentativa chega ao serviço — que recusa com
     * 409 porque a operação não é dele. A barreira que importa é a mesma de
     * sempre: `operation.terminal_id` tem de bater com o terminal autenticado.
     */
    public function test_wrong_terminal_cannot_confirm_another_rotation(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk();
        $other = Terminal::factory()->create();
        $other->tenant->update(['status' => 'ACTIVE', 'active' => true]);
        $otherToken = $this->credencialDeMaquina($other);
        $resposta = $this->lifecyclePost('terminal/credential/confirm', $otherToken, ['operation_id' => $payload['operation_id']]);
        $this->assertErroPdv($resposta, 409, 'credential_conflict');
        $this->assertDatabaseHas('terminal_credential_operations', ['id' => $payload['operation_id'], 'status' => 'PENDING']);
    }

    public function test_confirmation_failure_rolls_back_promotion_and_old_revocation(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $candidate = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk()->json('data.credential.access_token');
        DB::unprepared("CREATE TRIGGER reject_confirm BEFORE UPDATE ON terminal_credential_operations WHEN NEW.status = 'CONFIRMED' BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => $payload['operation_id']])->assertStatus(500);
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertDatabaseHas('terminal_credential_operations', ['status' => 'PENDING']);
        $this->getTerminal($old)->assertOk();
        $this->getTerminal($candidate)->assertForbidden();
    }

    public function test_expiry_between_authentication_and_prepare_is_rechecked(): void
    {
        $this->credencialDeMaquina();
        $token = $this->terminal->fresh()->tokens()->sole();
        $token->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->expectException(AuthenticationException::class);
        app(TerminalCredentialLifecycle::class)->prepare($this->terminal->fresh(), $token, (string) Str::uuid(), bin2hex(random_bytes(32)));
    }

    public function test_rotation_rate_limit_is_per_terminal_and_sanitized(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        for ($i = 0; $i < 12; $i++) {
            $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk();
        }
        $response = $this->lifecyclePost('terminal/credential/rotations', $old, $payload);
        $this->assertErroPdv($response, 429, 'rate_limited');
        $response->assertHeader('Retry-After');
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_candidate_cannot_prepare_and_wrong_confirmation_id_is_conflict(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $candidate = $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk()->json('data.credential.access_token');
        $this->lifecyclePost('terminal/credential/rotations', $candidate, $this->payload())->assertForbidden();
        $this->assertErroPdv($this->lifecyclePost('terminal/credential/confirm', $candidate, ['operation_id' => (string) Str::uuid()]), 409, 'credential_conflict');
    }

    public function test_pending_delivery_after_server_key_change_fails_without_replacing_old(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk();
        $this->app->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
        $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertConflict();
        $this->getTerminal($old)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_operation_uuid_cannot_select_other_tenant(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertOk();
        app(TenantContext::class)->clear();
        $other = Terminal::factory()->create();
        $other->tenant->update(['status' => 'ACTIVE', 'active' => true]);
        $otherToken = $this->credencialDeMaquina($other);
        $this->lifecyclePost('terminal/credential/rotations', $otherToken, $payload)->assertConflict();
        $this->assertSame(0, $other->tokens()->where('name', 'pdv-machine-pending')->count());
    }

    public function test_storage_error_logging_does_not_report_exception_arguments_or_credentials(): void
    {
        $old = $this->credencialDeMaquina();
        $payload = $this->payload();
        DB::unprepared("CREATE TRIGGER reject_log_probe BEFORE INSERT ON terminal_credential_operations BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        Log::spy();
        $this->withHeaders(['X-Request-ID' => 'safe-failure-probe']);
        $this->lifecyclePost('terminal/credential/rotations', $old, $payload)->assertStatus(500);
        Log::shouldHaveReceived('error')->with('PDV credential operation failed.', [
            'exception_type' => QueryException::class, 'request_id' => 'safe-failure-probe',
        ]);
        Log::shouldHaveReceived('error')->once();

    }
}
