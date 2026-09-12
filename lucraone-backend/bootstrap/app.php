<?php

use App\Http\Middleware\AutenticarWeb;
use App\Http\Middleware\RedirecionarSeAutenticado;
use App\Modules\Automation\Infrastructure\Console\PruneAutomationLogsCommand;
use App\Modules\Identity\Http\Middleware\ApiAuthenticationMiddleware;
use App\Modules\Reporting\Infrastructure\Console\SendSalesSummaryCommand;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantMiddleware;
use App\Modules\Tenancy\TenancyServiceProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        TenancyServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // A descoberta automática só varre app/Console/Commands; os comandos vivem
    // dentro dos módulos, então precisam ser registrados à mão.
    ->withCommands([
        PruneAutomationLogsCommand::class,
        SendSalesSummaryCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // ApiAuthenticationMiddleware converte AuthenticationException em JSON 401.
        // Fica restrito ao grupo api: no navegador queremos redirect para /login,
        // não um corpo JSON.
        $middleware->api(append: [
            ApiAuthenticationMiddleware::class,
        ]);

        $middleware->alias([
            'auth.web' => AutenticarWeb::class,
            'convidado' => RedirecionarSeAutenticado::class,

            // Resolve o estabelecimento da requisição. NÃO registrar como
            // middleware global: precisa rodar DEPOIS da autenticação, senão
            // não há usuário para validar o vínculo e um X-Tenant-ID de outro
            // estabelecimento passaria sem checagem.
            // Uso correto: ['auth:sanctum', 'tenant'] — nessa ordem.
            'tenant' => ResolveTenantMiddleware::class,
        ]);

        // SEC-04 E7: o contexto do estabelecimento precisa estar resolvido antes
        // do route binding, para que o TenantScope filtre entidades de outros tenants.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: AutenticarWeb::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }
        });
    })->create();
