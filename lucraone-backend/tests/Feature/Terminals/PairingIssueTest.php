<?php

namespace Tests\Feature\Terminals;

use App\Modules\Terminals\Application\IssueTerminalPairingCode;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use Illuminate\Support\Str;

class PairingIssueTest extends PairingTestCase
{
    public function test_pending_terminal_receives_only_hash_with_correct_expiration(): void
    {
        $issued = $this->issue();
        $row = TerminalPairingCode::findOrFail($issued->pairingId);
        [$selector, $secret] = explode('.', $issued->code);
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTVWXYZ]{6}\.[23456789ABCDEFGHJKMNPQRSTVWXYZ]{12}$/', $issued->code);
        $this->assertSame($selector, $row->selector);
        $this->assertSame(hash('sha256', $secret), $row->code_hash);
        $this->assertSame(now()->addMinutes(10)->toDateTimeString(), $row->expires_at->toDateTimeString());
        $this->assertSame(0, $row->attempts);
        $this->assertNull($row->consumed_at);
        $this->assertNull($row->invalidated_at);
        $this->assertArrayNotHasKey('code_hash', $row->toArray());
        $this->assertStringNotContainsString($secret, json_encode($row->getAttributes()));
        $this->assertStringNotContainsString($issued->code, json_encode($row->getAttributes()));
        $this->assertSame('PENDING', $this->terminal->fresh()->status);
        $this->assertTrue(Str::isUlid($row->id));
    }

    public function test_regeneration_invalidates_previous_pairing_and_keeps_history(): void
    {
        $old = $this->issue();
        $this->travel(1)->minutes();
        $new = $this->issue();
        $this->assertNotSame($old->code, $new->code);
        $this->assertNotNull(TerminalPairingCode::findOrFail($old->pairingId)->invalidated_at);
        $this->assertNull(TerminalPairingCode::findOrFail($new->pairingId)->invalidated_at);
        $this->assertSame(2, $this->terminal->pairingCodes()->count());
        $this->assertSame(now()->addMinutes(10)->toDateTimeString(), $new->expiresAt->toDateTimeString());
    }

    public function test_blocked_terminal_cannot_receive_code(): void
    {
        $this->terminal->update(['status' => 'BLOCKED']);
        $this->assertFailure('terminal-not-pairable', fn () => $this->issue());
    }

    public function test_revoked_terminal_cannot_receive_code(): void
    {
        $this->terminal->update(['status' => 'REVOKED']);
        $this->assertFailure('terminal-not-pairable', fn () => $this->issue());
    }

    public function test_active_terminal_cannot_receive_code(): void
    {
        $this->terminal->update(['status' => 'ACTIVE', 'installation_id' => (string) Str::uuid()]);
        $this->assertFailure('terminal-not-pairable', fn () => $this->issue());
    }

    public function test_pending_terminal_with_installation_cannot_receive_code(): void
    {
        $this->terminal->update(['installation_id' => (string) Str::uuid()]);
        $this->assertFailure('terminal-not-pairable', fn () => $this->issue());
    }

    public function test_expiration_uses_configuration(): void
    {
        config(['pdv.pairing.ttl_minutes' => 3]);
        $issued = $this->issue();
        $this->assertSame(now()->addMinutes(3)->toDateTimeString(), $issued->expiresAt->toDateTimeString());
    }

    public function test_issue_reloads_stale_terminal_state(): void
    {
        $stale = clone $this->terminal;
        $this->terminal->update(['status' => 'BLOCKED']);
        $this->assertFailure('terminal-not-pairable', fn () => app(IssueTerminalPairingCode::class)->issue($stale));
    }

    public function test_pairing_relationship_and_factory_have_no_plaintext_secret(): void
    {
        $row = TerminalPairingCode::factory()->create(['terminal_id' => $this->terminal->id]);
        $this->assertSame($this->terminal->id, $row->terminal->id);
        $this->assertSame(64, strlen($row->code_hash));
        $this->assertNull($row->consumed_at);
        $this->assertTrue($row->expires_at->isFuture());
        $this->assertTrue($this->terminal->pairingCodes->contains($row));
    }
}
