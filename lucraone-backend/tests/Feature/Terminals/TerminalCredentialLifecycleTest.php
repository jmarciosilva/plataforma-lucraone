<?php

namespace Tests\Feature\Terminals;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Terminals\Application\RevokeTerminalMachineCredentials;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\Route;

/**
 * PDV-BE-04 — ciclo de vida da credencial de máquina.
 *
 * Um Terminal operacional é uma instalação física, e a instalação é única e
 * imutável desde o PDV-BE-03. Logo há no máximo uma credencial vigente: emitir
 * de novo é rotação, não acúmulo.
 */
class TerminalCredentialLifecycleTest extends MachineTestCase
{
    private function rotaDeMaquina(): string
    {
        Route::middleware(['auth:sanctum', 'terminal.context'])
            ->get('/_pdvbe04/ciclo', fn () => response()->json(['ok' => true]));

        return '/_pdvbe04/ciclo';
    }

    public function test_primeira_emissao_deixa_um_token(): void
    {
        $this->emitir();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(1, $this->terminal->fresh()->tokens()->count());
    }

    public function test_segunda_emissao_continua_com_um_unico_token(): void
    {
        $this->emitir();
        $this->emitir();
        $this->emitir();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_rotacao_invalida_o_token_anterior_e_valida_o_novo(): void
    {
        $rota = $this->rotaDeMaquina();

        $antigo = $this->emitir();
        $this->requisicaoDeMaquina($rota, $antigo->plainTextToken)->assertOk();

        $novo = $this->emitir();

        $this->requisicaoDeMaquina($rota, $antigo->plainTextToken)->assertUnauthorized();
        $this->requisicaoDeMaquina($rota, $novo->plainTextToken)->assertOk();
        $this->assertNotSame($antigo->plainTextToken, $novo->plainTextToken);
    }

    /**
     * Sair de ACTIVE revoga: depois de um bloqueio, voltar a operar exige
     * credencial nova.
     *
     * A negação por requisição já barraria o acesso enquanto BLOCKED. A
     * remoção resolve o passo seguinte — o desbloqueio não pode ressuscitar
     * uma credencial que passou tempo fora de controle.
     */
    public function test_bloqueio_revoga_a_credencial(): void
    {
        $credencial = $this->emitir();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->terminal->fresh()->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->requisicaoDeMaquina($this->rotaDeMaquina(), $credencial->plainTextToken)
            ->assertUnauthorized();
    }

    public function test_desbloqueio_nao_ressuscita_credencial_antiga(): void
    {
        $rota = $this->rotaDeMaquina();
        $antigo = $this->emitir();

        $terminal = $this->terminal->fresh();
        $terminal->update(['status' => Terminal::STATUS_BLOCKED]);
        $terminal->fresh()->update(['status' => Terminal::STATUS_ACTIVE]);

        // De volta a ACTIVE, mas sem credencial: a antiga não volta.
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->requisicaoDeMaquina($rota, $antigo->plainTextToken)->assertUnauthorized();

        // E uma nova emissão funciona normalmente.
        $novo = $this->emitir();
        $this->requisicaoDeMaquina($rota, $novo->plainTextToken)->assertOk();
    }

    public function test_revogacao_remove_fisicamente_todos_os_tokens_do_terminal(): void
    {
        $credencial = $this->emitir();

        // Tokens históricos fabricados: a revogação alcança todos, não só o
        // último emitido.
        $terminal = $this->terminal->fresh();
        $terminal->createToken('pdv-machine', ['pdv:terminal:read']);
        $terminal->createToken('pdv-machine', ['pdv:terminal:read']);
        $this->assertDatabaseCount('personal_access_tokens', 3);

        $terminal->update(['status' => Terminal::STATUS_REVOKED]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->requisicaoDeMaquina($this->rotaDeMaquina(), $credencial->plainTextToken)
            ->assertUnauthorized();
    }

    public function test_terminal_revogado_nao_recebe_nova_credencial(): void
    {
        $this->terminal->fresh()->update(['status' => Terminal::STATUS_REVOKED]);

        $this->assertEmissaoRecusada('terminal-not-authenticable', fn () => $this->emitir());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_revogado_continua_irreversivel(): void
    {
        $terminal = $this->terminal->fresh();
        $terminal->update(['status' => Terminal::STATUS_REVOKED]);

        // Invariante do PDV-BE-02, preservada: REVOKED não volta.
        $this->expectException(InvalidTerminalAssignment::class);
        $terminal->fresh()->update(['status' => Terminal::STATUS_ACTIVE]);
    }

    public function test_revogacao_nao_alcanca_token_humano(): void
    {
        $credencialMaquina = $this->emitir();

        $pessoa = User::factory()->create(['status' => 'ACTIVE']);
        $tokenHumano = $pessoa->createToken('auth_token', ['business:read', 'business:write']);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $removidos = app(RevokeTerminalMachineCredentials::class)->revoke($this->terminal->fresh());

        $this->assertSame(1, $removidos);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $pessoa->id,
        ]);
        $this->assertNotNull($tokenHumano->accessToken->fresh());
        $this->assertNotSame('', $credencialMaquina->plainTextToken);
    }

    public function test_revogacao_de_um_terminal_nao_alcanca_outro(): void
    {
        $this->emitir();

        $outro = $this->ativar(Terminal::factory()->create());
        $outro->tenant->update(['status' => 'ACTIVE', 'active' => true]);
        $this->emitir($outro->fresh());

        $this->assertDatabaseCount('personal_access_tokens', 2);

        app(RevokeTerminalMachineCredentials::class)->revoke($this->terminal->fresh());

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $outro->id]);
    }

    public function test_credencial_vale_antes_do_prazo_e_nao_vale_depois(): void
    {
        $rota = $this->rotaDeMaquina();
        $credencial = $this->emitir();
        $ttl = (int) config('pdv.machine_credentials.ttl_minutes');

        $this->travel($ttl - 1)->minutes();
        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertOk();

        // Depois do prazo o guard recusa antes do callback. Aqui as duas
        // expirações do Sanctum vencem juntas, porque o TTL de máquina é
        // igual ao teto global — por isso nenhuma das duas é redundante por
        // acidente: é a mesma fronteira, declarada explicitamente.
        $this->travel(2)->minutes();
        $this->requisicaoDeMaquina($rota, $credencial->plainTextToken)->assertUnauthorized();
    }

    public function test_teto_global_do_sanctum_tambem_limita_o_token_de_maquina(): void
    {
        $rota = $this->rotaDeMaquina();

        // Credencial forjada com expires_at muito além do teto global: prova
        // que o teto medido sobre created_at continua valendo, e que declarar
        // validade acima dele não funcionaria.
        $terminal = $this->terminal->fresh();
        $token = $terminal->createToken('pdv-machine', ['pdv:terminal:read'], now()->addYear());

        $this->travel((int) config('sanctum.expiration') + 1)->minutes();

        $this->assertFalse($token->accessToken->fresh()->expires_at->isPast());
        $this->requisicaoDeMaquina($rota, $token->plainTextToken)->assertUnauthorized();
    }
}
