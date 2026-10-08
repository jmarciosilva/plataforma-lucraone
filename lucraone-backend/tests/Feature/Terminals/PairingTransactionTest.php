<?php

namespace Tests\Feature\Terminals;

use App\Modules\Terminals\Application\ConsumeTerminalPairingCode;
use App\Modules\Terminals\Application\IssueTerminalPairingCode;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use Illuminate\Support\Str;
use RuntimeException;

class PairingTransactionTest extends PairingTestCase
{
    public function test_failed_regeneration_preserves_previous_live_pairing(): void
    {
        $issued = $this->issue();
        TerminalPairingCode::creating(fn () => throw new RuntimeException('Injected issuance failure.'));
        try {
            app(IssueTerminalPairingCode::class)->issue($this->terminal);
            $this->fail('Expected injected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected issuance failure.', $exception->getMessage());
        } finally {
            TerminalPairingCode::clearBootedModels();
        }
        $this->assertNull(TerminalPairingCode::findOrFail($issued->pairingId)->invalidated_at);
        $this->assertDatabaseCount('terminal_pairing_codes', 1);
    }

    public function test_failure_marking_consumed_rolls_back_terminal_binding_and_attempt(): void
    {
        $issued = $this->issue();
        TerminalPairingCode::saving(function (TerminalPairingCode $pairing) {
            if ($pairing->isDirty('consumed_at')) {
                throw new RuntimeException('Injected storage failure.');
            }
        });
        try {
            app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid());
            $this->fail('Expected injected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected storage failure.', $exception->getMessage());
        } finally {
            TerminalPairingCode::clearBootedModels();
        }
        $this->assertNull($this->terminal->fresh()->installation_id);
        $this->assertSame('PENDING', $this->terminal->fresh()->status);
        $row = TerminalPairingCode::findOrFail($issued->pairingId);
        $this->assertNull($row->consumed_at);
        $this->assertSame(0, $row->attempts);
    }

    public function test_storage_failure_during_terminal_save_rolls_back_pairing(): void
    {
        $issued = $this->issue();
        Terminal::saving(function (Terminal $terminal) {
            if ($terminal->isDirty('installation_id')) {
                throw new RuntimeException('Injected Terminal failure.');
            }
        });
        try {
            app(ConsumeTerminalPairingCode::class)->consume($issued->code, (string) Str::uuid());
            $this->fail('Expected injected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected Terminal failure.', $exception->getMessage());
        } finally {
            Terminal::clearBootedModels();
        }
        $this->assertNull($this->terminal->fresh()->installation_id);
        $this->assertSame('PENDING', $this->terminal->fresh()->status);
        $this->assertNull(TerminalPairingCode::findOrFail($issued->pairingId)->consumed_at);
    }
}
