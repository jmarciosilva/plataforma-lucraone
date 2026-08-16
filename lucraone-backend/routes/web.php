<?php

use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\CategoryWebController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EstabelecimentoController;
use App\Http\Controllers\Web\InventoryWebController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\ProductWebController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\TenantController;
use App\Http\Controllers\Web\UserController;
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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/tenants/{tenant}/restore', [TenantController::class, 'restore'])
        ->name('tenants.restore');
    Route::resource('tenants', TenantController::class);

    Route::post('/users/{user}/restore', [UserController::class, 'restore'])
        ->name('users.restore');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->name('users.reset-password');
    Route::resource('users', UserController::class);

    Route::post('/companies/{company}/addresses', [CompanyController::class, 'storeAddress'])
        ->name('companies.addresses.store');
    Route::put('/companies/{company}/addresses/{address}', [CompanyController::class, 'updateAddress'])
        ->name('companies.addresses.update');
    Route::post('/companies/{company}/restore', [CompanyController::class, 'restore'])
        ->name('companies.restore');
    Route::resource('companies', CompanyController::class);

    Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
        ->name('roles.permissions.sync');
    Route::resource('roles', RoleController::class)->only(['index', 'show']);

    Route::get('/permissions', PermissionController::class)->name('permissions.index');

    Route::post('/products/{product}/restore', [ProductWebController::class, 'restore'])
        ->name('catalog.products.restore');
    Route::post('/products/{product}/prices', [ProductWebController::class, 'storePrice'])
        ->name('catalog.products.prices.store');
    Route::delete('/products/{product}/prices/{price}', [ProductWebController::class, 'destroyPrice'])
        ->name('catalog.products.prices.destroy');
    Route::resource('products', ProductWebController::class)
        ->names('catalog.products');

    Route::post('/categories/{category}/restore', [CategoryWebController::class, 'restore'])
        ->name('catalog.categories.restore');
    Route::resource('categories', CategoryWebController::class)
        ->names('catalog.categories');

    Route::post('/inventory/adjust', [InventoryWebController::class, 'adjust'])
        ->name('inventory.adjust');
    Route::post('/inventory/stock-levels', [InventoryWebController::class, 'storeStockLevel'])
        ->name('inventory.stock-levels.store');
    Route::get('/inventory', [InventoryWebController::class, 'index'])
        ->name('inventory.index');
    Route::get('/inventory/{inventory}', [InventoryWebController::class, 'show'])
        ->name('inventory.show');
});
