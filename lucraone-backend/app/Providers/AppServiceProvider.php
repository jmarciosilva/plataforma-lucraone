<?php

namespace App\Providers;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Authorization\Http\Policies\BranchPolicy;
use App\Modules\Authorization\Http\Policies\PermissionPolicy;
use App\Modules\Authorization\Http\Policies\RolePolicy;
use App\Modules\Automation\Application\Listeners\ProcessAutomationTrigger;
use App\Modules\Automation\Domain\Events\AutomationTriggered;
use App\Modules\Automation\Domain\Models\AutomationRule;
use App\Modules\Automation\Domain\Models\Notification;
use App\Modules\Automation\Http\Policies\AutomationRulePolicy;
use App\Modules\Automation\Http\Policies\NotificationPolicy;
use App\Modules\Branches\Domain\Models\Branch;
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
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Http\Policies\TerminalPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

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
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Terminal::class, TerminalPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(StockLevel::class, StockLevelPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(AutomationRule::class, AutomationRulePolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);

        // Relatórios são leitura agregada, sem modelo próprio — daí um Gate
        // nomeado em vez de uma Policy.
        Gate::define(
            'view-reports',
            fn (User $user) => $user->hasAnyPermission(['view-reports', 'manage-sales'])
        );

        // O módulo Automation escuta um evento só; registrar à mão é mais
        // explícito do que depender da descoberta automática de listeners.
        Event::listen(AutomationTriggered::class, ProcessAutomationTrigger::class);

        $this->configurarAutenticacaoDaApi();
    }

    /**
     * Freio do login da API e validade dos tokens já emitidos.
     */
    private function configurarAutenticacaoDaApi(): void
    {
        // Freio por IP no login da API. Limiter nomeado, e não `throttle:n,m`
        // solto na rota, para não criar um limite que pegue outros endpoints.
        // O teto por IP é mais alto que o por conta de propósito: um
        // estabelecimento atrás de uma única saída de rede tem várias pessoas
        // entrando, e quem trava o ataque a uma conta é o limite do
        // LoginRequest (5 por minuto, por e-mail + IP).
        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(20)
            ->by($request->ip())
            ->response(fn (Request $request, array $cabecalhos) => response()->json([
                'message' => 'Muitas tentativas de login. Tente novamente em instantes.',
            ], 429, $cabecalhos)));

        // Revogação central: um token já emitido para de valer no instante em
        // que a conta é desativada. Antes disto, desativar alguém não
        // alcançava os tokens dele — só barrava o acesso ao estabelecimento,
        // e apenas porque canAccessTenant() exige conta ativa.
        //
        // O gancho é do próprio Sanctum, então não há middleware de
        // autenticação paralelo: vale para toda rota com auth:sanctum.
        Sanctum::authenticateAccessTokensUsing(
            function (PersonalAccessToken $token, bool $valido): bool {
                if (! $valido) {
                    return false;
                }

                $dono = $token->tokenable;

                return $dono instanceof User ? $dono->isActive() : true;
            }
        );
    }
}
