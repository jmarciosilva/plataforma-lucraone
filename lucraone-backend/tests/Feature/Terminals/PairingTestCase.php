<?php

namespace Tests\Feature\Terminals;

use App\Modules\Terminals\Application\IssueTerminalPairingCode;
use App\Modules\Terminals\Domain\Exceptions\PairingFailed;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class PairingTestCase extends TestCase
{
    use RefreshDatabase;

    protected Terminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->terminal = Terminal::factory()->create();
        $this->terminal->tenant->update(['status' => 'ACTIVE', 'active' => true]);
    }

    protected function issue(): mixed
    {
        return app(IssueTerminalPairingCode::class)->issue($this->terminal);
    }

    protected function assertFailure(string $reason, callable $action): void
    {
        try {
            $action();
            $this->fail('Pairing unexpectedly succeeded.');
        } catch (PairingFailed $failure) {
            $this->assertSame($reason, $failure->reason);
            $this->assertSame('Pairing could not be completed.', $failure->getMessage());
        }
    }
}
