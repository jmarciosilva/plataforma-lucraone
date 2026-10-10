<?php

use App\Http\Middleware\AutenticarWeb;
use App\Http\Middleware\RedirecionarSeAutenticado;
use App\Modules\Audit\Http\Middleware\RequestCorrelationMiddleware;
use App\Modules\Automation\Infrastructure\Console\PruneAutomationLogsCommand;
use App\Modules\Identity\Http\Middleware\ApiAuthenticationMiddleware;
use App\Modules\Identity\Http\Middleware\EnsureTokenAbility;
use App\Modules\Identity\Infrastructure\Console\PromoverPlatformAdminCommand;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Reporting\Infrastructure\Console\SendSalesSummaryCommand;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantMiddleware;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Modules\Terminals\Domain\Exceptions\MachineCredentialRefused;
use App\Modules\Terminals\Domain\Exceptions\PairingFailed;
use App\Modules\Terminals\Http\Middleware\EnsureMachineTokenAbility;
use App\Modules\Terminals\Http\Middleware\ResolveTerminalContext;
use App\Modules\Terminals\TerminalsServiceProvider;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        TenancyServiceProvider::class,
        TerminalsServiceProvider::class,
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
        PromoverPlatformAdminCommand::class,
        PruneAutomationLogsCommand::class,
        SendSalesSummaryCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // PM-05: códigos classificatórios inválidos não são corrigidos por trim.
        // Campo vazio ainda vira NULL pelo ConvertEmptyStringsToNull existente.
        $middleware->trimStrings(except: ['ncm_code', 'cest_code', 'default_origin_code']);

        // Quem termina o TLS é o nginx de fora (o do host, na VPS), que repassa
        // em http para o nginx deste compose, que fala com o php-fpm pela rede
        // do Docker. Sem confiar nesses saltos o Laravel ignora
        // X-Forwarded-Proto, se julga em http e devolve redirect de login e URL
        // de asset com esquema errado — o navegador recusa como mixed content.
        //
        // Faixas privadas em vez de '*': o serviço app não publica porta no
        // host, então só um container da própria rede do compose alcança este
        // php-fpm. Nada vindo da Internet consegue forjar estes cabeçalhos.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);

        // ApiAuthenticationMiddleware converte AuthenticationException em JSON 401.
        // Fica restrito ao grupo api: no navegador queremos redirect para /login,
        // não um corpo JSON.
        $middleware->api(prepend: [
            RequestCorrelationMiddleware::class,
        ], append: [
            ApiAuthenticationMiddleware::class,
        ]);

        $middleware->alias([
            'auth.web' => AutenticarWeb::class,
            'convidado' => RedirecionarSeAutenticado::class,

            // Resolve o estabelecimento da requisição. NÃO registrar como
            // middleware global: precisa rodar DEPOIS da autenticação, senão
            // não há usuário para validar o vínculo e um X-Tenant-ID de outro
            // estabelecimento passaria sem checagem.
            // Uso correto: ['auth:sanctum', 'token.ability', 'tenant'] — o
            // 'tenant' sempre depois da autenticação.
            'tenant' => ResolveTenantMiddleware::class,

            // Confere a ability do token da requisição. Como o 'tenant',
            // precisa rodar DEPOIS da autenticação — sem token autenticado
            // não há nada a conferir.
            // Uso correto: ['auth:sanctum', 'token.ability', 'tenant'].
            'token.ability' => EnsureTokenAbility::class,

            // Caminho de máquina (PDV-BE-04), separado do humano. Exige
            // sujeito Terminal, recusa X-Tenant-ID e deriva contexto do
            // Terminal autenticado. Como os dois acima, roda DEPOIS da
            // autenticação.
            // Uso correto, nesta ordem:
            // ['auth:sanctum', 'terminal.context', 'machine.ability:pdv:terminal:read'].
            'terminal.context' => ResolveTerminalContext::class,

            // Ability da credencial de máquina, declarada na rota. Roda depois
            // de 'terminal.context': alcance do token não prova tipo de sujeito.
            'machine.ability' => EnsureMachineTokenAbility::class,
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

        // ----------------------------------------------------------------
        // Contrato público de erro do PDV (PDV-BE-05).
        //
        // Os renderizadores são avaliados na ORDEM DE REGISTRO e o primeiro
        // que devolve algo ganha, então estes vêm antes dos genéricos: cada um
        // devolve null fora de `api/v1/pdv/*`, e as outras rotas da API seguem
        // com o contrato que já tinham. O PDV é um cliente distribuído em
        // loja; a forma do erro dele precisa ser estável e pobre em informação.
        // ----------------------------------------------------------------
        $noPdv = fn (Request $request) => $request->is('api/v1/pdv/*');

        // Estrutura da requisição. É o único erro que detalha campo, porque o
        // cliente precisa saber o que corrigir — e as mensagens são nossas,
        // sem eco do valor enviado (o código de pareamento é segredo).
        $exceptions->render(function (ValidationException $e, Request $request) use ($noPdv) {
            if (! $noPdv($request)) {
                return null;
            }

            return PdvErrorResponse::make(
                $request,
                PdvErrorResponse::CODE_VALIDATION,
                'Dados da requisição inválidos.',
                422,
                errors: $e->errors(),
            );
        });

        // Falha de pareamento: resposta ÚNICA para todos os motivos.
        //
        // O domínio distingue expirado, consumido, invalidado, segredo errado,
        // selector inexistente e Terminal inadequado. Expor essa distinção
        // transformaria o endpoint em oráculo: saber que o selector existe mas
        // o segredo está errado diz ao atacante que ele acertou metade de um
        // código de 18 caracteres. O motivo real continua disponível no
        // domínio, para teste e para log interno.
        $exceptions->render(function (PairingFailed $e, Request $request) use ($noPdv) {
            if (! $noPdv($request)) {
                return null;
            }

            return PdvErrorResponse::make(
                $request,
                PdvErrorResponse::CODE_PAIRING_FAILED,
                'Não foi possível concluir o pareamento.',
                422,
            );
        });

        // Emissão de credencial recusada. Não deveria escapar — o consumo do
        // pareamento já a converte —, mas se escapar não pode virar 500 nem
        // revelar o motivo.
        $exceptions->render(function (MachineCredentialRefused $e, Request $request) use ($noPdv) {
            if (! $noPdv($request)) {
                return null;
            }

            return PdvErrorResponse::make(
                $request,
                PdvErrorResponse::CODE_PAIRING_FAILED,
                'Não foi possível concluir o pareamento.',
                422,
            );
        });

        // 401 genérico. Token ausente, inválido, expirado, rotacionado,
        // Terminal bloqueado/revogado, Tenant suspenso, Company ou Branch fora
        // de operação — tudo chega aqui pelo mesmo caminho (o callback do
        // Sanctum recusa e o guard não resolve sujeito) e sai com a mesma
        // resposta. Dizer qual das condições falhou informaria o estado do
        // estabelecimento a quem só tem um token velho na mão.
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($noPdv) {
            if (! $noPdv($request)) {
                return null;
            }

            return PdvErrorResponse::make(
                $request,
                PdvErrorResponse::CODE_UNAUTHENTICATED,
                'Credencial ausente ou inválida.',
                401,
            );
        });

        // Erro inesperado dentro de rota PDV: nada de classe, arquivo, SQL ou
        // stack trace, mesmo com APP_DEBUG ligado. O `request_id` é o que liga
        // a reclamação da loja ao log do servidor.
        //
        // Duas famílias ficam de fora, e as duas por necessidade:
        //
        // - HttpExceptionInterface são status deliberados (404, 405) e seguem
        //   o tratamento padrão do framework;
        // - HttpResponseException NÃO é erro: é o mecanismo pelo qual o Laravel
        //   carrega uma resposta já montada através da pilha, e é como o
        //   `throttle` entrega o 429 do nosso próprio limiter. Como o
        //   `renderViaCallbacks()` roda ANTES do `match` do handler que
        //   desembrulha essa exceção, um catch-all sem esta guarda transformaria
        //   o 429 em 500 — foi exatamente o que os testes de rate limit
        //   apontaram.
        $exceptions->render(function (Throwable $e, Request $request) use ($noPdv) {
            if (! $noPdv($request)
                || $e instanceof HttpExceptionInterface
                || $e instanceof HttpResponseException) {
                return null;
            }

            report($e);

            return PdvErrorResponse::make(
                $request,
                PdvErrorResponse::CODE_INTERNAL,
                'Erro inesperado ao processar a requisição.',
                500,
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado'], 401);
            }
        });
    })->create();
