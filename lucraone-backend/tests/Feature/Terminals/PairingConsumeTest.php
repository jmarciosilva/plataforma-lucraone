<?php

namespace Tests\Feature\Terminals;

use App\Modules\Terminals\Application\ConsumeTerminalPairingCode;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use Illuminate\Support\Str;

class PairingConsumeTest extends PairingTestCase
{
    public function test_valid_pairing_binds_installation_and_activates_without_credential(): void
    {
        $issued = $this->issue();
        $uuid = (string) Str::uuid();
        $result = app(ConsumeTerminalPairingCode::class)->consume($issued->code, strtoupper($uuid));
        $terminal = $this->terminal->fresh();
        $this->assertSame($uuid, $terminal->installation_id);
        $this->assertSame('ACTIVE', $terminal->status);
        $this->assertSame($terminal->id, $result->terminalId);
        $this->assertSame($terminal->tenant_id, $result->tenantId);
        $this->assertSame($terminal->company_id, $result->companyId);
        $this->assertSame($terminal->branch_id, $result->branchId);
        $this->assertSame($uuid, $result->installationId);
        $this->assertSame('ACTIVE', $result->status);
        $this->assertNotNull(TerminalPairingCode::findOrFail($issued->pairingId)->consumed_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertArrayNotHasKey('credential', get_object_vars($result));
    }

    public function test_expired_code_is_rejected_at_exact_boundary(): void
    {
        $issued = $this->issue();
        $this->travel(10)->minutes();
        $this->assertFailure('expired', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
        $this->assertSame('PENDING', $this->terminal->fresh()->status);
    }

    public function test_unknown_code_is_rejected(): void
    {
        $this->assertFailure('invalid-code', fn () => app(ConsumeTerminalPairingCode::class)->consume('222222.222222222222', (string) Str::uuid()));
    }

    public function test_malformed_code_is_rejected(): void
    {
        $this->assertFailure('invalid-code', fn () => app(ConsumeTerminalPairingCode::class)->consume('invalid', (string) Str::uuid()));
    }

    public function test_invalid_uuid_is_rejected_without_partial_binding(): void
    {
        $issued = $this->issue();
        $this->assertFailure('invalid-installation', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, 'invalid'));
        $this->assertNull($this->terminal->fresh()->installation_id);
        $this->assertNull(TerminalPairingCode::findOrFail($issued->pairingId)->consumed_at);
    }

    public function test_non_v4_uuid_is_rejected(): void
    {
        $issued = $this->issue();
        $this->assertFailure('invalid-installation', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, '123e4567-e89b-12d3-a456-426614174000'));
    }

    public function test_installation_bound_to_other_terminal_is_rejected_case_insensitively(): void
    {
        $uuid = (string) Str::uuid();
        Terminal::factory()->create(['installation_id' => $uuid]);
        $issued = $this->issue();
        $this->assertFailure('installation-already-bound', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, strtoupper($uuid)));
        $this->assertNull($this->terminal->fresh()->installation_id);
    }

    public function test_blocked_after_issue_cannot_consume(): void
    {
        $issued = $this->issue();
        $this->terminal->update(['status' => 'BLOCKED']);
        $this->assertFailure('terminal-not-pairable', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
    }

    public function test_revoked_after_issue_cannot_consume(): void
    {
        $issued = $this->issue();
        $this->terminal->update(['status' => 'REVOKED']);
        $this->assertFailure('terminal-not-pairable', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
    }

    public function test_existing_installation_is_never_overwritten(): void
    {
        $issued = $this->issue();
        $uuid = (string) Str::uuid();
        $this->terminal->update(['installation_id' => $uuid]);
        $this->assertFailure('terminal-not-pairable', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
        $this->assertSame($uuid, $this->terminal->fresh()->installation_id);
    }
}
