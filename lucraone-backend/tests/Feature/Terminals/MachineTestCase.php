<?php

namespace Tests\Feature\Terminals;

use App\Modules\Terminals\Application\IssuedTerminalMachineCredential;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Domain\Exceptions\MachineCredentialRefused;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Base das suítes de autenticação e credencial de máquina do PDV-BE-04.
 *
 * Monta um Terminal já pareado — ACTIVE, com installation_id — porque é esse o
 * único estado em que a credencial existe. O pairing em si tem suíte própria
 * (PairingTestCase); aqui o interesse começa depois dele.
 */
abstract class MachineTestCase extends TestCase
{
    use RefreshDatabase;

    protected Terminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->terminal = Terminal::factory()->create();
        $this->terminal->tenant->update(['status' => 'ACTIVE', 'active' => true]);
        $this->ativar($this->terminal);
    }

    /**
     * Leva um Terminal de PENDING a ACTIVE sem passar pelo pairing.
     *
     * Vincular a instalação e ativar é o efeito do consumo do código; repetir o
     * fluxo completo aqui só acrescentaria dependência de outra suíte.
     */
    protected function ativar(Terminal $terminal): Terminal
    {
        $terminal->update([
            'installation_id' => (string) Str::uuid(),
            'status' => Terminal::STATUS_ACTIVE,
        ]);

        return $terminal->fresh();
    }

    protected function emitir(?Terminal $terminal = null): IssuedTerminalMachineCredential
    {
        return app(IssueTerminalMachineCredential::class)->issue($terminal ?? $this->terminal->fresh());
    }

    /**
     * Faz uma requisição autenticada por credencial de máquina.
     *
     * O `forgetGuards()` não é detalhe: em produção cada requisição resolve o
     * guard do zero, mas no teste o RequestGuard vive no AuthManager e guarda
     * o sujeito já resolvido. Sem esquecê-lo, a segunda chamada dentro de um
     * mesmo teste devolveria o resultado da primeira sem reavaliar token nem
     * política — e um teste de enforcement POR REQUISIÇÃO passaria por engano,
     * justamente onde ele precisa ser rigoroso.
     */
    protected function requisicaoDeMaquina(string $rota, string $token, array $cabecalhos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->withHeaders($cabecalhos)->getJson($rota);
    }

    /**
     * Muda o estado de um dos três pais do Terminal.
     *
     * Escreve direto no model do pai, sem tocar no Terminal: o ponto dos testes
     * de enforcement é justamente que o token já emitido perde validade sem que
     * ninguém mexa nele.
     */
    protected function mudarEstrutura(string $entidade, string $status): void
    {
        $terminal = $this->terminal->fresh();

        match ($entidade) {
            'tenant' => $terminal->tenant->update(
                $status === 'DELETED'
                    ? ['status' => 'ACTIVE', 'active' => true]
                    : ['status' => $status, 'active' => $status !== 'SUSPENDED' && $status !== 'CANCELLED']
            ),
            'company' => $terminal->company->update(['status' => $status]),
            'branch' => $terminal->branch->update(['status' => $status]),
        };

        if ($entidade === 'tenant' && $status === 'DELETED') {
            $terminal->tenant->delete();
        }
    }

    protected function assertEmissaoRecusada(string $reason, callable $acao): void
    {
        try {
            $acao();
            $this->fail('Machine credential was unexpectedly issued.');
        } catch (MachineCredentialRefused $recusa) {
            $this->assertSame($reason, $recusa->reason);
            $this->assertSame('Machine credential could not be issued.', $recusa->getMessage());
        }
    }
}
