<?php

namespace Tests\Feature\Pdv;

use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use Illuminate\Support\Str;

/**
 * PDV-BE-05 — fluxo completo de integração, pelas rotas reais.
 *
 * Este é o teste que prova a etapa inteira: health → pareamento → credencial
 * → autenticação de máquina → contexto. Nenhuma chamada direta a controller ou
 * service; tudo pelo HTTP, porque é o HTTP que o aplicativo Java vai consumir.
 *
 * É também o único lugar onde as cinco fases da trilha aparecem juntas:
 * BranchPolicy/correlação (PDV-BE-01), Terminal e vínculos (PDV-BE-02),
 * pareamento (PDV-BE-03), credencial e enforcement (PDV-BE-04) e contratos
 * HTTP (PDV-BE-05).
 */
class PdvEndToEndTest extends PdvTestCase
{
    public function test_fluxo_completo_de_bootstrap_do_pdv(): void
    {
        $terminal = $this->terminal->fresh();
        $uuidInstalacao = (string) Str::uuid();

        // 1. O aplicativo confirma que alcançou o backend certo.
        $this->getJson('/api/v1/pdv/health')
            ->assertOk()
            ->assertJsonPath('data.api', 'pdv')
            ->assertJsonPath('data.version', 'v1');

        // 2. A administração emitiu um código para este Terminal pré-cadastrado.
        $emitido = $this->emitirPairing();
        $this->assertSame('PENDING', $terminal->status);

        // 3. O operador digita o código no caixa, que envia com o UUID da
        //    instalação. Rota real, pública, com freio por IP e por selector.
        $pareamento = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => $uuidInstalacao,
        ])->assertOk();

        // 4. O backend ativou o Terminal, vinculou a instalação, consumiu o
        //    código e emitiu a credencial — tudo na mesma transação.
        $pareado = $this->terminal->fresh();
        $this->assertSame('ACTIVE', $pareado->status);
        $this->assertSame($uuidInstalacao, $pareado->installation_id);
        $this->assertNotNull(TerminalPairingCode::findOrFail($emitido->pairingId)->consumed_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $credencial = $pareamento->json('data.credential.access_token');
        $this->assertNotEmpty($credencial);
        $this->assertStringContainsString('no-store', $pareamento->headers->get('Cache-Control'));

        // 5. O caixa guarda a credencial e passa a se identificar com ela.
        $contexto = $this->getTerminal($credencial)->assertOk();

        // 6. O contexto devolvido é exatamente o do Terminal autenticado —
        //    derivado do token, sem cabeçalho de tenant em nenhum momento.
        $contexto->assertJsonPath('data.terminal.id', $pareado->id)
            ->assertJsonPath('data.terminal.status', 'ACTIVE')
            ->assertJsonPath('data.terminal.installation_id', $uuidInstalacao)
            ->assertJsonPath('data.tenant.id', $pareado->tenant_id)
            ->assertJsonPath('data.company.id', $pareado->company_id)
            ->assertJsonPath('data.branch.id', $pareado->branch_id);

        // Os IDs do contexto batem com os que o pareamento prometeu.
        foreach (['terminal', 'tenant', 'company', 'branch'] as $entidade) {
            $this->assertSame(
                $pareamento->json("data.$entidade.id"),
                $contexto->json("data.$entidade.id"),
                "ID de $entidade divergiu entre pareamento e contexto."
            );
        }

        // 7. E a credencial não é recuperável: o GET não devolve token.
        $this->assertNull($contexto->json('data.credential.access_token'));
        $this->assertStringNotContainsString($credencial, $contexto->getContent());

        // 8. Replay imediato do mesmo código falha e não gera segunda
        //    credencial — a primeira continua sendo a única válida.
        $this->assertErroPdv(
            $this->postPair(['pairing_code' => $emitido->code, 'installation_id' => (string) Str::uuid()]),
            422,
            'pairing_failed'
        );
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->getTerminal($credencial)->assertOk();
    }

    public function test_correlacao_atravessa_o_fluxo_inteiro(): void
    {
        $emitido = $this->emitirPairing();

        $this->getJson('/api/v1/pdv/health', ['X-Request-ID' => 'e2e-health'])
            ->assertOk()
            ->assertHeader('X-Request-ID', 'e2e-health');

        $pareamento = $this->postPair(
            ['pairing_code' => $emitido->code, 'installation_id' => (string) Str::uuid()],
            ['X-Request-ID' => 'e2e-pair']
        )->assertOk()->assertHeader('X-Request-ID', 'e2e-pair');

        $this->getTerminal($pareamento->json('data.credential.access_token'), ['X-Request-ID' => 'e2e-terminal'])
            ->assertOk()
            ->assertHeader('X-Request-ID', 'e2e-terminal');
    }

    public function test_credencial_do_fluxo_real_morre_com_o_terminal_bloqueado(): void
    {
        $emitido = $this->emitirPairing();

        $credencial = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk()->json('data.credential.access_token');

        $this->getTerminal($credencial)->assertOk();

        // A administração bloqueia o caixa. A requisição seguinte do PDV cai,
        // sem ninguém tocar no token do lado do cliente.
        $this->terminal->fresh()->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->assertErroPdv($this->getTerminal($credencial), 401, 'unauthenticated');
    }

    public function test_dois_terminais_nao_se_enxergam(): void
    {
        // Isolamento no caminho real: cada credencial só vê o próprio caixa,
        // mesmo quando os dois pertencem ao mesmo estabelecimento.
        $outro = Terminal::factory()->forBranch($this->terminal->branch)->create(['name' => 'Caixa 002']);

        $credencialA = $this->postPair([
            'pairing_code' => $this->emitirPairing()->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk()->json('data.credential.access_token');

        $credencialB = $this->postPair([
            'pairing_code' => $this->emitirPairing($outro)->code,
            'installation_id' => (string) Str::uuid(),
        ], [], '198.51.100.77')->assertOk()->json('data.credential.access_token');

        $this->getTerminal($credencialA)
            ->assertOk()
            ->assertJsonPath('data.terminal.id', $this->terminal->id)
            ->assertJsonPath('data.terminal.name', 'Caixa 001');

        $this->getTerminal($credencialB)
            ->assertOk()
            ->assertJsonPath('data.terminal.id', $outro->id)
            ->assertJsonPath('data.terminal.name', 'Caixa 002');
    }
}
