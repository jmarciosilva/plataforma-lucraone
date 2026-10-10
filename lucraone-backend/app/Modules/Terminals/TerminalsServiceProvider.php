<?php

namespace App\Modules\Terminals;

use App\Modules\Terminals\Application\RevokeTerminalMachineCredentials;
use App\Modules\Terminals\Application\TerminalContext;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\ServiceProvider;

class TerminalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mesmo padrão do TenantContext: uma instância por requisição,
        // compartilhada por middleware, policies e serviços.
        $this->app->singleton(TerminalContext::class, fn () => new TerminalContext);
    }

    public function boot(): void
    {
        // Sair de ACTIVE apaga as credenciais de máquina do Terminal.
        //
        // A negação por requisição (TerminalAuthenticationEligibility, no
        // callback do Sanctum) já é a garantia principal: um Terminal BLOCKED
        // ou REVOKED não autentica nem com token íntegro na mão. Apagar é
        // defesa em profundidade, e resolve o caso em que o desbloqueio
        // ressuscitaria uma credencial que passou tempo fora de controle —
        // depois de um BLOCKED, voltar a operar exige credencial nova.
        //
        // Para REVOKED a remoção física é requisito, não só higiene: a
        // identidade saiu de operação em definitivo e o validador não permite
        // reativá-la.
        //
        // Limite conhecido, igual ao do validador de vínculos: o gancho é
        // Eloquent. SQL direto, bulk update e saveQuietly mudam status sem
        // passar por aqui e não são fluxo oficial de administração.
        Terminal::updated(function (Terminal $terminal): void {
            if ($terminal->wasChanged('status') && $terminal->status !== Terminal::STATUS_ACTIVE) {
                app(RevokeTerminalMachineCredentials::class)->revoke($terminal);
            }
        });
    }
}
