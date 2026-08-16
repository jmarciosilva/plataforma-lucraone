<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\EstabelecimentoController;
use Illuminate\Support\Facades\Route;

/*
| Rotas do painel administrativo.
|
| Três níveis de acesso:
|   - visitante          → login
|   - autenticado        → escolha de estabelecimento
|   - com estabelecimento → o painel em si
*/

Route::get('/', fn () => redirect()->route('dashboard'));

// Visitante
Route::middleware('convidado')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

/*
| Autenticado, ainda sem estabelecimento definido.
|
| Estas rotas não podem exigir estabelecimento resolvido: são justamente elas
| que o definem. Daí o parâmetro "sem-tenant".
*/
Route::middleware('auth.web:sem-tenant')->group(function () {
    Route::get('/estabelecimentos', [EstabelecimentoController::class, 'escolher'])
        ->name('estabelecimentos.escolher');

    Route::post('/estabelecimentos', [EstabelecimentoController::class, 'definir'])
        ->name('estabelecimentos.definir');
});

// Painel: exige estabelecimento em uso
Route::middleware('auth.web')->group(function () {
    Route::view('/dashboard', 'dashboard.index')->name('dashboard');
});
