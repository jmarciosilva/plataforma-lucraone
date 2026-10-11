<?php

namespace App\Modules\Pdv\Infrastructure\Console;

use App\Modules\Pdv\Application\OperatorSessionAudit;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Marca como EXPIRED as sessões operacionais que passaram do prazo.
 *
 * Higiene, não segurança: a expiração é aplicada pela autenticação a cada
 * requisição, independentemente desta reconciliação — uma sessão vencida não
 * autentica nem se este comando nunca rodar. O que ele resolve é a linha que
 * fica ACTIVE porque ninguém mais tentou usá-la, deixando o relatório de turnos
 * e a listagem de caixas ocupados mentirem sobre o estado da loja.
 *
 * Mantém histórico: nada é apagado. Não é agendado automaticamente; agendar
 * depois do deploy e da migration, em ambiente aprovado — mesma orientação do
 * `pdv:expire-credential-operations`.
 */
final class ExpireOperatorSessions extends Command
{
    protected $signature = 'pdv:expire-operator-sessions';

    protected $description = 'Mark overdue PDV operator sessions as expired, preserving history';

    public function handle(OperatorSessionAudit $auditoria): int
    {
        $total = 0;

        PdvOperatorSession::withoutGlobalScopes()
            ->where('status', PdvOperatorSession::STATUS_ACTIVE)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($sessoes) use ($auditoria, &$total) {
                foreach ($sessoes as $sessao) {
                    DB::transaction(function () use ($sessao, $auditoria, &$total) {
                        $atual = PdvOperatorSession::withoutGlobalScopes()
                            ->whereKey($sessao->id)
                            ->lockForUpdate()
                            ->first();

                        // Relido sob lock: entre a seleção e a transação a
                        // sessão pode ter sido encerrada por logout.
                        if ($atual === null || ! $atual->isActive() || $atual->expires_at->isFuture()) {
                            return;
                        }

                        $atual->update(['status' => PdvOperatorSession::STATUS_EXPIRED]);

                        $auditoria->sessao(
                            OperatorSessionAudit::SESSION_EXPIRED,
                            $atual->fresh(),
                            $atual->user,
                            ['reason' => 'absolute-deadline'],
                        );

                        $total++;
                    }, 3);
                }
            });

        $this->info("Sessões de operador expiradas: {$total}");

        return self::SUCCESS;
    }
}
