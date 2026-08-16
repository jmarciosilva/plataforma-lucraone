<?php

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\TenantResolver;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar TenantContext como singleton
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        // Registrar TenantResolver
        $this->app->singleton(TenantResolver::class, function ($app) {
            return new TenantResolver(
                $app->make(TenantContext::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
