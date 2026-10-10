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
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
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
use App\Modules\Terminals\Application\TerminalAuthenticationEligibility;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\PairingCode;
use App\Modules\Terminals\Http\Policies\TerminalPolicy;
use App\Modules\Terminals\TerminalsServiceProvider;
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
        $this->app->register(TerminalsServiceProvider::class);
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

        // Freio do pareamento de PDV, em duas dimensões. Nenhuma das duas
        // substitui a outra, e nenhuma substitui os `attempts` persistentes do
        // PDV-BE-03:
        //
        // - os attempts travam o ataque a UM código, mas não impedem varrer
        //   muitos códigos diferentes;
        // - o freio por IP trava o volume de uma origem, mas não um ataque
        //   distribuído;
        // - o freio por selector trava a insistência contra um alvo, mesmo
        //   vindo de muitos IPs — e o atacante só troca de selector às custas
        //   de voltar a enfrentar o freio por IP.
        //
        // As janelas acompanham o domínio: o código vive 10 minutos, então
        // contar em 10 minutos é contar exatamente a vida útil do alvo. O teto
        // por selector é 5, igual ao `max_attempts`, para que o freio de HTTP
        // não seja mais frouxo que o do banco; o teto por IP é mais alto porque
        // uma loja pode ter vários caixas sendo instalados atrás de uma única
        // saída de rede — mesma lógica do 'api-login'.
        //
        // O IP é confiável nesta infraestrutura: o nginx do host acrescenta o
        // endereço real à direita do X-Forwarded-For e o Symfony lê dessa ponta,
        // ignorando entradas injetadas pelo cliente (verificado contra a stack
        // real antes de escrever este limiter).
        RateLimiter::for('pdv-pairing', function (Request $request) {
            $limites = [
                Limit::perMinutes(10, 20)
                    ->by('pdv-pair:ip:'.$request->ip())
                    ->response($this->recusaPorVolume()),
            ];

            // Só a parte pública do código entra na chave. O segredo não vai
            // para cache em nenhuma hipótese; código sem selector plausível
            // fica apenas sob o freio por IP.
            $codigo = $request->input('pairing_code');
            $selector = is_string($codigo) ? PairingCode::selectorFrom($codigo) : null;

            if ($selector !== null) {
                $limites[] = Limit::perMinutes(10, 5)
                    ->by('pdv-pair:selector:'.hash('sha256', $selector))
                    ->response($this->recusaPorVolume());
            }

            return $limites;
        });

        // Revogação central: um token já emitido para de valer no instante em
        // que a conta é desativada. Antes disto, desativar alguém não
        // alcançava os tokens dele — só barrava o acesso ao estabelecimento,
        // e apenas porque canAccessTenant() exige conta ativa.
        //
        // O gancho é do próprio Sanctum, então não há middleware de
        // autenticação paralelo: vale para toda rota com auth:sanctum.
        //
        // A decisão é POR TIPO DE SUJEITO e fail-closed. Até o PDV-BE-04 o
        // ramo final era `: true`: qualquer tokenable que não fosse User
        // autenticava só porque o guard considerou o token válido, sem
        // nenhuma checagem de estado. O guard não fecha essa porta — com
        // `auth.guards.sanctum.provider` nulo, o `hasValidProvider()` do
        // Sanctum aceita qualquer tokenable —, então este callback é o único
        // ponto de controle. Sujeito desconhecido não autentica.
        Sanctum::authenticateAccessTokensUsing(
            function (PersonalAccessToken $token, bool $valido): bool {
                if (! $valido) {
                    return false;
                }

                $dono = $token->tokenable;

                // Humano: comportamento do SEC-02, preservado integralmente.
                if ($dono instanceof User) {
                    return $dono->isActive();
                }

                // Máquina: política própria, reavaliada a cada requisição.
                // Status do Terminal e estado operacional de Tenant, Company e
                // Branch entram aqui, e não só na emissão — bloquear um
                // Terminal ou suspender o estabelecimento precisa derrubar a
                // credencial no mesmo instante, sem esperar expiração.
                if ($dono instanceof Terminal) {
                    return app(TerminalAuthenticationEligibility::class)->allows($dono);
                }

                return false;
            }
        );
    }

    /**
     * Resposta pública de 429 do pareamento.
     *
     * Estável e muda: não diz se o selector existe, se há Terminal por trás,
     * nem quantas tentativas restam no banco — qualquer uma dessas viraria
     * oráculo. O Retry-After vem dos cabeçalhos que o próprio throttle calcula.
     */
    private function recusaPorVolume(): callable
    {
        return fn (Request $request, array $cabecalhos) => PdvErrorResponse::make(
            $request,
            PdvErrorResponse::CODE_RATE_LIMITED,
            'Muitas tentativas. Tente novamente mais tarde.',
            429,
            headers: $cabecalhos,
        );
    }
}
