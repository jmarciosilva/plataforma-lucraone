<?php

namespace Tests\Integration\Terminals;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Application\RevokeTerminalMachineCredentials;
use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalCredentialOperation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class CredentialConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private Terminal $terminal;

    private PersonalAccessToken $old;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pdv-lifecycle-mysql', config('database.connections.mysql.host'));
        $this->assertSame('pdv_lifecycle_test', DB::connection()->getDatabaseName());
        $this->terminal = Terminal::factory()->create(['status' => 'ACTIVE', 'installation_id' => (string) Str::uuid()]);
        $this->terminal->tenant->update(['status' => 'ACTIVE', 'active' => true]);
        app(IssueTerminalMachineCredential::class)->issue($this->terminal);
        $this->old = $this->terminal->tokens()->sole();
    }

    /** Independent connections/processes, released by a common socket barrier. */
    private function race(array $jobs): array
    {
        DB::disconnect();
        $children = [];
        foreach ($jobs as $job) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();
            if ($pid === 0) {
                foreach ($children as $child) {
                    fclose($child[1]);
                }
                fclose($pair[0]);
                fread($pair[1], 1);
                DB::purge();
                try {
                    $result = ['ok' => true, 'result' => $job()];
                } catch (\Throwable $error) {
                    // Never serialize exception messages, SQL bindings or tokens.
                    $result = ['ok' => false, 'type' => get_class($error)];
                }
                fwrite($pair[1], json_encode($result));
                fclose($pair[1]);
                exit(0);
            }
            $this->assertGreaterThan(0, $pid);
            fclose($pair[1]);
            $children[] = [$pid, $pair[0]];
        }
        foreach ($children as $child) {
            fwrite($child[1], 'x');
            stream_set_timeout($child[1], 30);
        }
        $results = [];
        foreach ($children as [$pid, $socket]) {
            $results[] = json_decode(stream_get_contents($socket), true);
            fclose($socket);
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        DB::purge();

        return $results;
    }

    private function prepare(string $id, string $secret): string
    {
        $result = app(TerminalCredentialLifecycle::class)->prepare($this->terminal, $this->old, $id, $secret);

        return hash('sha256', $result->credential);
    }

    public function test_identical_concurrent_prepares_return_same_candidate(): void
    {
        $id = (string) Str::uuid();
        $secret = bin2hex(random_bytes(32));
        $results = $this->race([fn () => $this->prepare($id, $secret), fn () => $this->prepare($id, $secret)]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result'], $results[1]['result']);
        $this->assertDatabaseCount('terminal_credential_operations', 1);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_distinct_concurrent_prepares_have_one_winner(): void
    {
        $results = $this->race([fn () => $this->prepare((string) Str::uuid(), bin2hex(random_bytes(32))),
            fn () => $this->prepare((string) Str::uuid(), bin2hex(random_bytes(32)))]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertDatabaseCount('terminal_credential_operations', 1);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_simultaneous_confirmations_are_idempotent(): void
    {
        $id = (string) Str::uuid();
        app(TerminalCredentialLifecycle::class)->prepare($this->terminal, $this->old, $id, bin2hex(random_bytes(32)));
        $candidate = $this->terminal->tokens()->where('name', 'pdv-machine-pending')->sole();
        $confirm = fn () => app(TerminalCredentialLifecycle::class)->confirm($this->terminal, $candidate, $id)->credentialExpiresAt;
        $results = $this->race([$confirm, $confirm]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result'], $results[1]['result']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_revocation_racing_prepare_leaves_no_credential_or_live_pending_operation(): void
    {
        $results = $this->race([
            fn () => $this->prepare((string) Str::uuid(), bin2hex(random_bytes(32))),
            fn () => app(RevokeTerminalMachineCredentials::class)->revoke($this->terminal),
        ]);
        $this->assertTrue($results[1]['ok']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(0, TerminalCredentialOperation::whereIn('status', ['ISSUED', 'PENDING'])->count());
    }

    public function test_revocation_racing_confirmation_leaves_no_credential(): void
    {
        $id = (string) Str::uuid();
        app(TerminalCredentialLifecycle::class)->prepare($this->terminal, $this->old, $id, bin2hex(random_bytes(32)));
        $candidate = $this->terminal->tokens()->where('name', 'pdv-machine-pending')->sole();
        $results = $this->race([
            fn () => app(TerminalCredentialLifecycle::class)->confirm($this->terminal, $candidate, $id)->credentialExpiresAt,
            fn () => app(RevokeTerminalMachineCredentials::class)->revoke($this->terminal),
        ]);
        $this->assertTrue($results[1]['ok']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function recoveryCode(): string
    {
        $actor = User::factory()->forTenant($this->terminal->tenant)->create();
        app(ProvisionarEstabelecimento::class)->provisionarMatriz($this->terminal->tenant);
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->terminal->tenant, $actor);
        app(TenantContext::class)->set($this->terminal->tenant_id);

        return app(TerminalCredentialLifecycle::class)->authorizeRecovery($actor, $this->terminal, 'lost')['recovery_code'];
    }

    public function test_concurrent_identical_recovery_does_not_duplicate_candidate(): void
    {
        $code = $this->recoveryCode();
        $secret = bin2hex(random_bytes(32));
        $job = fn () => hash('sha256', app(TerminalCredentialLifecycle::class)->recover($code, $this->terminal->installation_id, $secret)->credential);
        $results = $this->race([$job, $job]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['result'], $results[1]['result']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_concurrent_recovery_with_different_secrets_has_one_winner(): void
    {
        $code = $this->recoveryCode();
        $job = fn () => hash('sha256', app(TerminalCredentialLifecycle::class)->recover($code, $this->terminal->installation_id, bin2hex(random_bytes(32)))->credential);
        $results = $this->race([$job, $job]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_blocking_racing_recovery_never_restores_active_terminal(): void
    {
        $code = $this->recoveryCode();
        $results = $this->race([
            fn () => hash('sha256', app(TerminalCredentialLifecycle::class)->recover($code, $this->terminal->installation_id, bin2hex(random_bytes(32)))->credential),
            function () {
                $this->terminal->fresh()->update(['status' => 'BLOCKED']);

                return 'blocked';
            },
        ]);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame('BLOCKED', $this->terminal->fresh()->status);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
