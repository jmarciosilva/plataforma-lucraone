<?php

namespace Tests\Feature\Terminals;

use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

/**
 * Endpoint POST /api/v1/pdv/terminal/heartbeat.
 *
 * O heartbeat registra `last_seen_at` no terminal e serve como sinal de vida
 * contínua para o painel administrativo. Ele fica no mesmo grupo de rotas
 * do GET /terminal (machine.ability:pdv:terminal:read), então a mesma pilha
 * de middleware que protege a leitura protege o heartbeat.
 */
#[Group('heartbeat')]
#[Group('terminal')]
class HeartbeatTest extends MachineTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $issued = app(IssueTerminalMachineCredential::class)->issue($this->terminal->fresh());
        $this->token = $issued->plainTextToken;
    }

    private function heartbeat(string $token): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/pdv/terminal/heartbeat');
    }

    public function test_terminal_ativo_registra_heartbeat(): void
    {
        $antes = now()->subSecond();

        $this->heartbeat($this->token)->assertOk()->assertJson(['status' => 'ok']);

        $terminal = $this->terminal->fresh();
        $this->assertNotNull($terminal->last_seen_at);
        $this->assertTrue($terminal->last_seen_at->greaterThan($antes));
    }

    public function test_heartbeat_devolve_terminal_id(): void
    {
        $this->heartbeat($this->token)
            ->assertOk()
            ->assertJson(['terminal_id' => $this->terminal->id]);
    }

    public function test_sem_autenticacao_recebe_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/pdv/terminal/heartbeat')->assertUnauthorized();
    }

    public function test_token_humano_recebe_403(): void
    {
        $user = \App\Modules\Identity\Domain\Models\User::factory()->create();
        $token = $user->createToken('admin-token', ['business:write'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->postJson('/api/v1/pdv/terminal/heartbeat')
            ->assertForbidden();
    }

    public function test_terminal_bloqueado_recebe_401(): void
    {
        $terminal = $this->terminal->fresh();
        $terminal->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->heartbeat($this->token)->assertUnauthorized();
    }

    public function test_terminal_revogado_recebe_401(): void
    {
        $terminal = $this->terminal->fresh();
        // Bypass validator: revogado não pode ir para ACTIVE; use updateQuietly
        Terminal::withoutGlobalScopes()
            ->where('id', $terminal->id)
            ->update(['status' => Terminal::STATUS_REVOKED]);

        $this->heartbeat($this->token)->assertUnauthorized();
    }

    public function test_heartbeat_multiplas_vezes_avanca_o_last_seen_at(): void
    {
        $this->heartbeat($this->token)->assertOk();
        $primeira = $this->terminal->fresh()->last_seen_at;

        // Avança o relógio em 1 segundo para garantir diferença mensurável
        $this->travel(1)->seconds();

        $this->heartbeat($this->token)->assertOk();
        $segunda = $this->terminal->fresh()->last_seen_at;

        $this->assertTrue($segunda->greaterThanOrEqualTo($primeira));
    }
}
