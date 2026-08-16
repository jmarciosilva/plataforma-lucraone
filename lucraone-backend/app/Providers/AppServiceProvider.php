<?php

namespace App\Providers;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Http\Policies\UserPolicy;
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
    }
}
