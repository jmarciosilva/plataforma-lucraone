<?php

namespace App\Modules\Pdv;

use App\Modules\Pdv\Application\Listeners\EndOperatorSessionsOnCredentialRevocation;
use App\Modules\Pdv\Application\Listeners\KeepOperatorSessionThroughRotation;
use App\Modules\Pdv\Application\OperatorSessionContext;
use App\Modules\Pdv\Application\OperatorSessionLifecycle;
use App\Modules\Terminals\Domain\Events\TerminalCredentialRotated;
use App\Modules\Terminals\Domain\Events\TerminalMachineCredentialsRevoked;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PdvServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mesmo padrão do TenantContext e do TerminalContext: uma instância por
        // requisição, compartilhada por middleware, controller e auditoria.
        $this->app->singleton(OperatorSessionContext::class, fn () => new OperatorSessionContext);
    }

    public function boot(): void
    {
        // Rotação legítima da credencial de máquina religa o turno em vez de
        // derrubá-lo. Registrado à mão, como os outros listeners do projeto, e
        // SÍNCRONO: ele participa da transação que confirma a rotação.
        Event::listen(TerminalCredentialRotated::class, KeepOperatorSessionThroughRotation::class);

        // E o contrário: credencial APAGADA encerra o turno. Recuperação
        // administrativa, bloqueio e revogação não herdam sessão humana.
        Event::listen(TerminalMachineCredentialsRevoked::class, EndOperatorSessionsOnCredentialRevocation::class);

        // Sair de ACTIVE encerra o turno em andamento no Terminal.
        //
        // Defesa em profundidade, não a garantia. A garantia é dupla e já
        // existe sem este gancho: o callback do Sanctum recusa a credencial de
        // máquina de um Terminal fora de ACTIVE, e o gancho do módulo Terminals
        // apaga os PATs dele — o que faz a sessão perder a amarração com o PAT
        // que a abriu e ser recusada na revalidação por requisição.
        //
        // O que este gancho acrescenta é encerrar a linha IMEDIATAMENTE, em vez
        // de deixá-la ACTIVE até alguém tentar usá-la: um lease offline já
        // emitido vale até o prazo dele, e marcar a sessão revogada agora é o
        // que faz a próxima reconexão recusar em vez de revalidar.
        //
        // Limite conhecido, igual ao dos outros ganchos Eloquent do projeto:
        // SQL direto, bulk update e saveQuietly não passam por aqui, e não são
        // fluxo oficial de administração.
        Terminal::updated(function (Terminal $terminal): void {
            if ($terminal->wasChanged('status') && $terminal->status !== Terminal::STATUS_ACTIVE) {
                app(OperatorSessionLifecycle::class)->revokeForTerminal($terminal);
            }
        });
    }
}
