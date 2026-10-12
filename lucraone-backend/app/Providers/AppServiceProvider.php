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
use App\Modules\Identity\Infrastructure\Auth\IdentityUserProvider;
use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Inventory\Domain\Models\StockLevel;
use App\Modules\Inventory\Http\Policies\InventoryPolicy;
use App\Modules\Inventory\Http\Policies\StockLevelPolicy;
use App\Modules\Pdv\Domain\OperatorUsername;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Pdv\PdvServiceProvider;
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
use Illuminate\Support\Facades\Auth;
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
        $this->app->register(PdvServiceProvider::class);
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

        // Driver de identidade: o `eloquent` recusando credencial de
        // identificação nula ou vazia. Registrado aqui porque é `boot()` que o
        // AuthManager consulta ao resolver o provider configurado.
        Auth::provider(
            'identity',
            fn ($app, array $config) => new IdentityUserProvider($app['hash'], $config['model'])
        );
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

        RateLimiter::for('pdv-credential', fn (Request $request) => Limit::perMinute(12)
            ->by('pdv-credential:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response($this->recusaPorVolume()));
        RateLimiter::for('pdv-recovery-admin', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by('pdv-recovery-admin:'.$request->user()?->getAuthIdentifier())
            ->response($this->recusaPorVolume()));
        RateLimiter::for('pdv-recovery', function (Request $request) {
            $limits = [Limit::perMinutes(10, 20)->by('pdv-recovery:ip:'.$request->ip())->response($this->recusaPorVolume())];
            $code = $request->input('recovery_code');
            $selector = is_string($code) ? PairingCode::selectorFrom($code) : null;
            if ($selector !== null) {
                // More than five requests allows delivery retries; persistent bad attempts still stop at five.
                $limits[] = Limit::perMinutes(10, 10)->by('pdv-recovery:selector:'.hash('sha256', $selector))->response($this->recusaPorVolume());
            }

            return $limits;
        });

        // ----------------------------------------------------------------
        // Freios do operador de PDV (Fase 5).
        //
        // Quatro dimensões no login, e nenhuma substitui as outras:
        //
        // - por TERMINAL, porque um caixa comprometido é o atacante mais
        //   provável e já chega autenticado como máquina;
        // - por IP, para o volume de uma origem;
        // - por IDENTIFICADOR + terminal, que é o freio clássico de ataque a
        //   uma conta específica num caixa específico;
        // - por IDENTIFICADOR agregado, que é o único que enxerga a tentativa
        //   DISTRIBUÍDA: cem caixas tentando a mesma conta uma vez cada passam
        //   por todos os outros três.
        //
        // O e-mail nunca entra em chave de cache em claro — só o resumo dele.
        // Os limites são por janela de 10 minutos onde a intenção é frear
        // insistência, e por minuto onde a intenção é frear rajada.
        //
        // Este freio é HTTP e complementa o do LoginRequest humano; nenhum dos
        // dois substitui a revalidação por requisição, que é o que derruba
        // acesso já concedido.
        RateLimiter::for('pdv-operator-login', function (Request $request) {
            $terminal = (string) ($request->user()?->getAuthIdentifier() ?? 'anon');
            $username = $request->input('username');

            // A chave é o resumo da forma CANÔNICA do username, não o valor
            // cru. Canônica porque é por ela que o login resolve — senão
            // `jose.silva` e `josesilva` teriam contadores separados e o freio
            // por conta valeria o dobro. Resumo porque chave de cache não é
            // lugar de identificador legível.
            $identificador = is_string($username)
                ? hash('sha256', OperatorUsername::canonical($username))
                : null;

            $limites = [
                Limit::perMinute(10)->by('pdv-op-login:terminal:'.$terminal)->response($this->recusaPorVolume()),
                Limit::perMinutes(10, 30)->by('pdv-op-login:ip:'.$request->ip())->response($this->recusaPorVolume()),
            ];

            if ($identificador !== null) {
                $limites[] = Limit::perMinute(5)
                    ->by('pdv-op-login:id-terminal:'.$identificador.':'.$terminal)
                    ->response($this->recusaPorVolume());
                $limites[] = Limit::perMinutes(10, 10)
                    ->by('pdv-op-login:id:'.$identificador)
                    ->response($this->recusaPorVolume());
            }

            return $limites;
        });

        // Revalidação é barata e esperada com frequência — o caixa a usa para
        // renovar a graça offline. O teto existe para que ela não vire canal de
        // sondagem de credenciais de sessão.
        RateLimiter::for('pdv-operator-session', fn (Request $request) => Limit::perMinute(30)
            ->by('pdv-op-session:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response($this->recusaPorVolume()));

        RateLimiter::for('pdv-catalog', fn (Request $request) => Limit::perMinute(120)
            // O middleware de throttle roda antes de auth:sanctum. Usar o hash
            // do bearer permite um bucket por credencial sem guardar/logar o token.
            ->by('pdv-catalog:'.(is_string($request->bearerToken()) ? hash('sha256', $request->bearerToken()) : 'ip:'.$request->ip()))
            ->response($this->recusaPorVolume()));

        RateLimiter::for('pdv-catalog-publish', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by('pdv-catalog-publish:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response($this->recusaPorVolume()));

        // Administração humana: volume de tela, não de integração.
        RateLimiter::for('pdv-operator-admin', fn (Request $request) => Limit::perMinutes(10, 60)
            ->by('pdv-op-admin:'.($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response($this->recusaPorVolume()));

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
