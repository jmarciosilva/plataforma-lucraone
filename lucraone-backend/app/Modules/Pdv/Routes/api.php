<?php

use App\Modules\Pdv\Http\Controllers\CredentialLifecycleController;
use App\Modules\Pdv\Http\Controllers\CurrentTerminalController;
use App\Modules\Pdv\Http\Controllers\PairTerminalController;
use App\Modules\Pdv\Http\Controllers\PdvHealthController;
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
});
