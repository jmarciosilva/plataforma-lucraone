<?php

use App\Modules\Pdv\Http\Controllers\CredentialLifecycleController;
use App\Modules\Pdv\Http\Controllers\CurrentTerminalController;
use App\Modules\Pdv\Http\Controllers\OperatorBranchAssignmentController;
use App\Modules\Pdv\Http\Controllers\OperatorSessionController;
use App\Modules\Pdv\Http\Controllers\PairTerminalController;
use App\Modules\Pdv\Http\Controllers\PdvHealthController;
use App\Modules\Pdv\Http\Middleware\AuthenticateOperatorSession;
use App\Modules\Pdv\Http\Middleware\HumanAdministrationContext;
use App\Modules\Pdv\Http\Middleware\RecoveryAdministrationContext;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Http\Middleware\EnsureCredentialAbility;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/pdv')->group(function () {
    // Público: o aplicativo de loja precisa confirmar que alcançou o backend
    // certo antes de ter qualquer credencial.
    Route::get('health', PdvHealthController::class)->name('pdv.health');

    // Público por necessidade: antes do pareamento o Terminal não tem
    // credencial, então não há sujeito a autenticar. O freio é o
    // `throttle:pdv-pairing`, que limita por IP e por selector, somado ao
    // prazo, uso único e attempts persistentes do domínio.
    Route::post('terminals/pair', PairTerminalController::class)
        ->middleware('throttle:pdv-pairing')
        ->name('pdv.terminals.pair');

    Route::post('terminal/credential/recover', [CredentialLifecycleController::class, 'recover'])
        ->middleware('throttle:pdv-recovery')->name('pdv.credential.recover');

    Route::post('admin/terminals/{terminal}/credential-recovery-codes', [CredentialLifecycleController::class, 'authorizeRecovery'])
        ->middleware(['auth:sanctum', RecoveryAdministrationContext::class, 'throttle:pdv-recovery-admin'])
        ->name('pdv.credential.authorize');

    // ----------------------------------------------------------------
    // Administração HUMANA das atribuições de operador a filiais.
    //
    // Caminho de pessoa, não de máquina: PAT humano com `business:write` e
    // estabelecimento selecionado em X-Tenant-ID, validado pelo TenantResolver.
    // Credencial de Terminal não administra atribuição, e por isso estas rotas
    // ficam fora do grupo `terminal.context` — que recusaria sujeito humano.
    // ----------------------------------------------------------------
    Route::middleware(['auth:sanctum', HumanAdministrationContext::class, 'throttle:pdv-operator-admin'])->group(function () {
        Route::get('admin/branches/{branch}/operators', [OperatorBranchAssignmentController::class, 'index'])
            ->name('pdv.operators.index');
        Route::post('admin/branches/{branch}/operators', [OperatorBranchAssignmentController::class, 'store'])
            ->name('pdv.operators.store');
        Route::delete('admin/branches/{branch}/operators/{user}', [OperatorBranchAssignmentController::class, 'destroy'])
            ->name('pdv.operators.destroy');
        Route::get('admin/operators/{user}/branches', [OperatorBranchAssignmentController::class, 'operatorBranches'])
            ->name('pdv.operators.branches');
    });

    Route::middleware(['auth:sanctum', 'terminal.context', 'throttle:pdv-credential'])->group(function () {
        Route::post('terminal/credential/rotations', [CredentialLifecycleController::class, 'prepare'])
            ->middleware(EnsureCredentialAbility::class.':renew')->name('pdv.credential.prepare');
        Route::post('terminal/credential/confirm', [CredentialLifecycleController::class, 'confirm'])
            ->middleware(EnsureCredentialAbility::class.':confirm')->name('pdv.credential.confirm');
    });

    // A ordem importa e é a mesma documentada no PDV-BE-04: autenticar,
    // estabelecer o sujeito-máquina e seu contexto, e só então conferir o
    // alcance do token. Ability depois do contexto porque alcance de token não
    // prova tipo de sujeito — um token humano com a ability certa precisa parar
    // no `terminal.context`, não passar por ele.
    Route::middleware([
        'auth:sanctum',
        'terminal.context',
        'machine.ability:'.MachineTokenAbility::TERMINAL_READ,
    ])->group(function () {
        Route::get('terminal', CurrentTerminalController::class)->name('pdv.terminal');
    });

    // ----------------------------------------------------------------
    // Sessão operacional do operador (Fase 5).
    //
    // A pilha completa, na ordem em que cada pergunta pode ser respondida:
    //
    //   auth:sanctum                      — existe credencial válida?
    //   terminal.context                  — o sujeito é um Terminal, e qual
    //                                       estabelecimento/empresa/filial é
    //                                       dele? (X-Tenant-ID é recusado)
    //   machine.ability:pdv:operator-session
    //                                     — esta credencial de máquina alcança
    //                                       o fluxo de operador? Credencial
    //                                       legada (só `pdv:terminal:read`) para
    //                                       aqui, e precisa rotacionar antes.
    //   throttle:pdv-operator-login       — freio do login, só na criação
    //   pdv.operator.session              — existe operador autenticado nesta
    //                                       sessão, ainda autorizado AGORA?
    //
    // O login é a única rota do grupo sem o último middleware: é ele que cria a
    // sessão. As outras três exigem as DUAS identidades.
    // ----------------------------------------------------------------
    Route::middleware([
        'auth:sanctum',
        'terminal.context',
        'machine.ability:'.MachineTokenAbility::OPERATOR_SESSION,
    ])->group(function () {
        Route::post('operator-sessions', [OperatorSessionController::class, 'store'])
            ->middleware('throttle:pdv-operator-login')
            ->name('pdv.operator.sessions.store');

        Route::middleware(AuthenticateOperatorSession::class)->group(function () {
            Route::get('operator-session', [OperatorSessionController::class, 'show'])
                ->name('pdv.operator.session.show');
            Route::post('operator-session/revalidate', [OperatorSessionController::class, 'revalidate'])
                ->middleware('throttle:pdv-operator-session')
                ->name('pdv.operator.session.revalidate');
        });

        // O logout fica FORA do middleware de sessão, de propósito.
        //
        // Aquele middleware recusa sessão encerrada, expirada, revogada ou
        // amarrada a outra credencial de máquina — e é exatamente nesses
        // estados que o cliente mais precisa conseguir fechar o turno e limpar
        // o estado local. Atrás dele, o logout repetido devolveria 401 em vez
        // de 204, e o contrato prometido ao Java seria impossível de cumprir.
        //
        // A verificação não desaparece, só muda de lugar: o serviço exige
        // credencial conhecida E do mesmo terminal/instalação antes de encerrar
        // qualquer coisa. Credencial desconhecida ou de outro caixa continua
        // sendo 401, e encerrar sessão é sempre a direção segura.
        Route::delete('operator-session', [OperatorSessionController::class, 'destroy'])
            ->middleware('throttle:pdv-operator-session')
            ->name('pdv.operator.session.destroy');
    });
});
