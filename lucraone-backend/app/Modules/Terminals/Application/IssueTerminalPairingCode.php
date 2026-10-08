<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Terminals\Domain\Exceptions\PairingFailed;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use App\Modules\Terminals\Domain\PairingCode;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class IssueTerminalPairingCode
{
    // A camada administrativa futura deve autorizar via TerminalPolicy antes.
    public function issue(Terminal $terminal): IssuedTerminalPairingCode
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($terminal) {
                    $locked = Terminal::withoutGlobalScopes()->whereKey($terminal->id)->lockForUpdate()->first();
                    if ($locked === null) {
                        throw new PairingFailed('terminal-not-pairable');
                    }
                    app(PairingEligibility::class)->ensure($locked);
                    TerminalPairingCode::where('terminal_id', $locked->id)
                        ->whereNull('consumed_at')->whereNull('invalidated_at')
                        ->update(['invalidated_at' => now(), 'updated_at' => now()]);
                    $selector = PairingCode::random(6);
                    $secret = PairingCode::random(12);
                    $pairing = TerminalPairingCode::create([
                        'terminal_id' => $locked->id,
                        'selector' => $selector,
                        'code_hash' => hash('sha256', $secret),
                        'expires_at' => now()->addMinutes((int) config('pdv.pairing.ttl_minutes')),
                    ]);

                    return new IssuedTerminalPairingCode($pairing->id, $selector.'.'.$secret, $pairing->expires_at);
                }, 3);
            } catch (UniqueConstraintViolationException $exception) {
                if (! str_contains($exception->getMessage(), 'terminal_pairing_codes_selector_unique')
                    && ! str_contains($exception->getMessage(), 'terminal_pairing_codes.selector')) {
                    throw new PairingFailed('storage-failure');
                }
                // Colisão de selector: rollback preserva o código anterior.
            } catch (QueryException) {
                throw new PairingFailed('storage-failure');
            }
        }

        throw new PairingFailed('storage-failure');
    }
}
