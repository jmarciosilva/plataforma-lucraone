<?php

namespace Tests\Feature\Pdv;

use App\Modules\Terminals\Application\IssuedTerminalPairingCode;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Application\IssueTerminalPairingCode;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Base das suítes HTTP do PDV-BE-05.
 *
 * Tudo aqui passa pelas rotas reais: o ponto desta etapa é o contrato HTTP, e
 * um teste que chamasse controller ou service direto não provaria middleware,
 * rate limiter, envelope de erro nem cabeçalho.
 */
abstract class PdvTestCase extends TestCase
{
    use RefreshDatabase;

    protected Terminal $terminal;

    /**
     * IP público de documentação (RFC 5737), fora de qualquer faixa confiada.
     *
     * Em produção o IP do cliente chega como a entrada mais à direita do
     * X-Forwarded-For, acrescentada pelo nginx do host, e o Symfony lê dessa
     * ponta. No teste o equivalente fiel é o REMOTE_ADDR: um endereço público
     * não é proxy confiado, então o XFF é ignorado e `$request->ip()` devolve
     * exatamente este valor — mesma origem de autoridade que em produção.
     * Simular cliente via X-Forwarded-For seria incoerente com a infra.
     */
    protected const IP_CLIENTE = '198.51.100.42';

    protected function setUp(): void
    {
        parent::setUp();
        $this->terminal = Terminal::factory()->create(['name' => 'Caixa 001']);
        $this->terminal->tenant->update(['status' => 'ACTIVE', 'active' => true]);

        // Não há limiter a limpar: o phpunit.xml fixa CACHE_STORE=array, que
        // nasce vazio em cada boot da aplicação — ou seja, em cada teste. Se
        // algum dia a suíte passar a usar um store persistente, a contagem
        // passará a atravessar testes e este ponto precisará de limpeza
        // explícita, como o SEC-02 faz com o limiter de login.
        $this->assertSame('array', config('cache.default'));
    }

    protected function emitirPairing(?Terminal $terminal = null): IssuedTerminalPairingCode
    {
        return app(IssueTerminalPairingCode::class)->issue($terminal ?? $this->terminal->fresh());
    }

    /**
     * Terminal já pareado, com credencial de máquina válida.
     */
    protected function credencialDeMaquina(?Terminal $terminal = null): string
    {
        $terminal ??= $this->terminal;
        $terminal->update([
            'installation_id' => (string) Str::uuid(),
            'status' => Terminal::STATUS_ACTIVE,
        ]);

        return app(IssueTerminalMachineCredential::class)->issue($terminal->fresh())->plainTextToken;
    }

    /**
     * POST no endpoint real de pairing, como um cliente da Internet.
     */
    protected function postPair(array $payload, array $cabecalhos = [], string $ip = self::IP_CLIENTE): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders($cabecalhos)
            ->postJson('/api/v1/pdv/terminals/pair', $payload);
    }

    /**
     * GET no endpoint real do Terminal autenticado.
     *
     * O `forgetGuards()` reproduz o isolamento que cada requisição tem em
     * produção: o RequestGuard vive no AuthManager e guarda o sujeito já
     * resolvido, então sem isso uma segunda chamada no mesmo teste devolveria
     * o resultado da primeira sem reavaliar token nem política.
     */
    protected function getTerminal(?string $token = null, array $cabecalhos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        $req = $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])->withHeaders($cabecalhos);

        if ($token !== null) {
            $req = $req->withToken($token);
        }

        return $req->getJson('/api/v1/pdv/terminal');
    }

    /**
     * O envelope de erro público, com request_id igual ao cabeçalho.
     */
    protected function assertErroPdv(TestResponse $resposta, int $status, string $code): void
    {
        $resposta->assertStatus($status)
            ->assertJsonPath('error.code', $code)
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id']]);

        $cabecalho = $resposta->headers->get('X-Request-ID');
        $this->assertNotEmpty($cabecalho, 'Erro do PDV precisa de X-Request-ID no cabeçalho.');
        $resposta->assertJsonPath('error.request_id', $cabecalho);

        // Nenhum erro público carrega dado interno.
        $corpo = $resposta->getContent();
        foreach (['SQLSTATE', 'vendor/', '/app/', 'Exception', 'stack'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $corpo);
        }
    }
}
