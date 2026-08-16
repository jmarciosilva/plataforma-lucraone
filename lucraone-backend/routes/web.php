<?php

use Illuminate\Support\Facades\Route;

/*
| Rotas web do painel administrativo (FASE 03).
|
| F3.1 entrega apenas o esqueleto: rota raiz, placeholder do dashboard
| e o stub de /login. A autenticação real chega no F3.2.
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Stub — substituído pelo LoginController no F3.2
Route::get('/login', function () {
    return view('auth.login');
})->middleware('convidado')->name('login');

Route::post('/logout', function () {
    auth()->guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::middleware('auth.web')->group(function () {
    Route::view('/dashboard', 'dashboard.index')->name('dashboard');
});

/*
| ⚠️ ROTA TEMPORÁRIA — BYPASS DE AUTENTICAÇÃO ⚠️
|
| Existe só para permitir ver o painel enquanto o login real não está pronto.
| Autentica o primeiro usuário do banco sem pedir senha.
|
| Restrita ao ambiente local. DEVE SER APAGADA no sprint F3.2, junto com
| a entrada correspondente no checklist do roadmap.
*/
if (app()->environment('local')) {
    Route::get('/preview-login', function () {
        auth()->guard('web')->login(
            \App\Modules\Identity\Domain\Models\User::withoutGlobalScopes()->first()
        );

        return redirect()->route('dashboard');
    })->name('preview-login');
}
