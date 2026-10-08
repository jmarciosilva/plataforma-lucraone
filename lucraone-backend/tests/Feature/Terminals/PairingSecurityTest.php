<?php

namespace Tests\Feature\Terminals;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Terminals\Application\ConsumeTerminalPairingCode;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

class PairingSecurityTest extends PairingTestCase
{
    public function test_consumed_code_rejects_replay_with_same_or_other_installation(): void
    {
        $issued = $this->issue();
        $uuid = (string) Str::uuid();
        app(ConsumeTerminalPairingCode::class)->consume($issued->code, $uuid);
        foreach ([$uuid, (string) Str::uuid()] as $id) {
            $this->assertFailure('consumed', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, $id));
        }
        $this->assertSame($uuid, $this->terminal->fresh()->installation_id);
    }

    public function test_rotated_code_cannot_consume(): void
    {
        $old = $this->issue();
        $new = $this->issue();
        $this->assertFailure('invalidated', fn () => app(ConsumeTerminalPairingCode::class)->consume($old->code, (string) Str::uuid()));
        $this->assertSame('ACTIVE', app(ConsumeTerminalPairingCode::class)->consume($new->code, (string) Str::uuid())->status);
    }

    public function test_five_wrong_secrets_persist_and_disable_pairing(): void
    {
        $issued = $this->issue();
        [$selector, $secret] = explode('.', $issued->code);
        $wrong = $selector.'.'.($secret[0] === '2' ? '3' : '2').substr($secret, 1);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertFailure('invalid-code', fn () => app(ConsumeTerminalPairingCode::class)->consume($wrong, (string) Str::uuid()));
            $this->assertSame($attempt, TerminalPairingCode::findOrFail($issued->pairingId)->attempts);
        }
        $this->assertNotNull(TerminalPairingCode::findOrFail($issued->pairingId)->invalidated_at);
        $this->assertFailure('attempts-exceeded', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
        $this->assertSame('PENDING', $this->terminal->fresh()->status);
    }

    public function test_last_allowed_attempt_can_succeed(): void
    {
        $issued = $this->issue();
        TerminalPairingCode::findOrFail($issued->pairingId)->update(['attempts' => 4]);
        $this->assertSame('ACTIVE', app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid())->status);
    }

    public function test_attempt_limit_uses_configuration(): void
    {
        config(['pdv.pairing.max_attempts' => 1]);
        $issued = $this->issue();
        $this->assertFailure('invalid-installation', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, 'bad'));
        $this->assertFailure('attempts-exceeded', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
    }

    public function test_unknown_selector_does_not_increment_unrelated_pairing(): void
    {
        $issued = $this->issue();
        $this->assertFailure('invalid-code', fn () => app(ConsumeTerminalPairingCode::class)->consume('222222.222222222222', (string) Str::uuid()));
        $this->assertSame(0, TerminalPairingCode::findOrFail($issued->pairingId)->attempts);
    }

    public function test_installation_cannot_change_after_successful_pairing(): void
    {
        $issued = $this->issue();
        app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid());
        $terminal = $this->terminal->fresh();
        $this->expectException(InvalidTerminalAssignment::class);
        $terminal->update(['installation_id' => (string) Str::uuid()]);
    }

    public function test_bound_installation_cannot_be_cleared(): void
    {
        $issued = $this->issue();
        app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid());
        $this->expectException(InvalidTerminalAssignment::class);
        $this->terminal->fresh()->update(['installation_id' => null]);
    }

    public function test_same_uuid_in_uppercase_keeps_binding(): void
    {
        $issued = $this->issue();
        $uuid = (string) Str::uuid();
        app(ConsumeTerminalPairingCode::class)->consume($issued->code, $uuid);
        $terminal = $this->terminal->fresh();
        $terminal->update(['installation_id' => strtoupper($uuid)]);
        $this->assertSame($uuid, $terminal->fresh()->installation_id);
    }

    public function test_lowercase_code_is_accepted_without_changing_secret_identity(): void
    {
        $issued = $this->issue();
        $this->assertSame('ACTIVE', app(ConsumeTerminalPairingCode::class)->consume(strtolower($issued->code), (string) Str::uuid())->status);
    }

    public function test_parent_assignment_changed_after_issue_is_rejected(): void
    {
        $issued = $this->issue();
        $otherCompany = Company::factory()->create();
        $this->terminal->branch->update(['company_id' => $otherCompany->id]);
        $this->assertFailure('invalid-assignment', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
        $this->assertNull($this->terminal->fresh()->installation_id);
        $this->assertNull(TerminalPairingCode::findOrFail($issued->pairingId)->consumed_at);
    }

    public function test_pairing_does_not_log_or_audit_secret(): void
    {
        Log::spy();
        $issued = $this->issue();
        app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid());
        foreach (['info', 'debug', 'warning', 'error', 'critical', 'log'] as $method) {
            Log::shouldNotHaveReceived($method);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('unavailableStructures')]
    public function test_inactive_structure_blocks_consumption(string $entity, string $status): void
    {
        $issued = $this->issue();
        $this->changeStructure($entity, $status);
        $this->assertFailure('structure-unavailable', fn () => app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid()));
        $this->assertNull($this->terminal->fresh()->installation_id);
    }

    #[DataProvider('unavailableStructures')]
    public function test_inactive_structure_blocks_issuance(string $entity, string $status): void
    {
        $this->changeStructure($entity, $status);
        $this->assertFailure('structure-unavailable', fn () => $this->issue());
    }

    public static function unavailableStructures(): array
    {
        return [
            ['tenant', 'SUSPENDED'], ['tenant', 'CANCELLED'], ['tenant', 'DELETED'],
            ['tenant', 'DISABLED'], ['company', 'INACTIVE'], ['company', 'SUSPENDED'],
            ['branch', 'INACTIVE'], ['branch', 'SUSPENDED'],
        ];
    }

    private function changeStructure(string $entity, string $status): void
    {
        $parent = $this->terminal->$entity;
        if ($status === 'DELETED') {
            $parent->delete();
        } elseif ($status === 'DISABLED') {
            $parent->update(['active' => false]);
        } else {
            $parent->update(['status' => $status]);
        }
    }

    public function test_trial_tenant_with_active_flag_can_pair(): void
    {
        $this->terminal->tenant->update(['status' => 'TRIAL']);
        $issued = $this->issue();
        $this->assertSame('ACTIVE', app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid())->status);
    }
}
