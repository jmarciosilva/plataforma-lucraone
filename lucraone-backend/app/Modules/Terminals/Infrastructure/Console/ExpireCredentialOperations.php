<?php

namespace App\Modules\Terminals\Infrastructure\Console;

use App\Modules\Terminals\Application\TerminalCredentialLifecycle;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalCredentialOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ExpireCredentialOperations extends Command
{
    protected $signature = 'pdv:expire-credential-operations';

    protected $description = 'Expire pending credential operations without removing audit history';

    public function handle(TerminalCredentialLifecycle $lifecycle): int
    {
        TerminalCredentialOperation::whereIn('status', ['ISSUED', 'PENDING'])->where('expires_at', '<=', now())
            ->select('terminal_id')->distinct()->chunkById(100, function ($rows) use ($lifecycle) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, $lifecycle) {
                        $terminal = Terminal::withoutGlobalScopes()->whereKey($row->terminal_id)->lockForUpdate()->first();
                        if ($terminal) {
                            $lifecycle->expirePending($terminal);
                        }
                    }, 3);
                }
            }, 'terminal_id', 'terminal_id');

        return self::SUCCESS;
    }
}
