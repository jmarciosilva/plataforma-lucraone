<?php

namespace App\Providers;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Authorization\Http\Policies\PermissionPolicy;
use App\Modules\Authorization\Http\Policies\RolePolicy;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Companies\Http\Policies\CompanyPolicy;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Policies\UserPolicy;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Inventory\Http\Policies\InventoryPolicy;
use App\Modules\Inventory\Http\Policies\StockLevelPolicy;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Http\Policies\CategoryPolicy;
use App\Modules\Products\Http\Policies\ProductPolicy;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;
use App\Modules\Sales\Http\Policies\CustomerPolicy;
use App\Modules\Sales\Http\Policies\OrderPolicy;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Http\Policies\TenantPolicy;
use App\Modules\Tenancy\TenancyServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar módulos
        $this->app->register(TenancyServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);

        // Relatórios são leitura agregada, sem modelo próprio — daí um Gate
        // nomeado em vez de uma Policy.
        Gate::define(
            'view-reports',
            fn (User $user) => $user->hasAnyPermission(['view-reports', 'manage-sales', 'create-role'])
        );
    }
}
