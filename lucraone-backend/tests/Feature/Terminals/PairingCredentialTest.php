<?php

namespace Tests\Feature\Terminals;

use App\Modules\Terminals\Application\ConsumeTerminalPairingCode;
use App\Modules\Terminals\Application\IssueTerminalMachineCredential;
use App\Modules\Terminals\Application\RevokeTerminalMachineCredentials;
use App\Modules\Terminals\Application\TerminalAuthenticationEligibility;
use App\Modules\Terminals\Domain\Exceptions\MachineCredentialRefused;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * PDV-BE-04 — o pairing passa a emitir a credencial de máquina.
 *
 * Um pairing que ativasse o Terminal sem entregar credencial deixaria um PDV
 * pareado que não autentica e sem como repetir o processo: o código é de uso
 * único e o installation_id é imutável. Por isso as duas coisas acontecem na
 * mesma transação, e é essa indivisibilidade que esta suíte prova.
 *
 * O endpoint HTTP que vai expor isso é do PDV-BE-05; aqui o fluxo é exercitado
 * pelo serviço de aplicação.
 */
class PairingCredentialTest extends PairingTestCase
{
    private function consumir(string $codigo, ?string $uuid = null): mixed
    {
        return app(ConsumeTerminalPairingCode::class)->consume($codigo, $uuid ?? (string) Str::uuid());
    }

    public function test_sucesso_entrega_credencial_junto_do_provisionamento(): void
    {
        $issued = $this->issue();

        $result = $this->consumir($issued->code);

        $this->assertNotSame('', $result->credential);
        $this->assertNotNull($result->credentialExpiresAt);
        $this->assertSame(
            (int) config('pdv.machine_credentials.ttl_minutes'),
            (int) now()->diffInMinutes($result->credentialExpiresAt)
        );

        // Terminal ativo, código consumido, instalação vinculada e exatamente
        // uma credencial — tudo observável depois do mesmo commit.
        $terminal = $this->terminal->fresh();
        $this->assertSame('ACTIVE', $terminal->status);
        $this->assertNotNull($terminal->installation_id);
        $this->assertNotNull(TerminalPairingCode::findOrFail($issued->pairingId)->consumed_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(1, $terminal->tokens()->count());
    }

    public function test_credencial_entregue_pelo_pairing_autentica(): void
    {
        Route::middleware(['auth:sanctum', 'terminal.context', 'machine.ability:pdv:terminal:read'])
            ->get('/_pdvbe04/pareado', fn () => response()->json(['ok' => true]));

        $issued = $this->issue();
        $result = $this->consumir($issued->code);

        $this->app['auth']->forgetGuards();
        $this->withToken($result->credential)
            ->getJson('/_pdvbe04/pareado')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_credencial_do_pairing_tem_a_ability_de_maquina(): void
    {
        $issued = $this->issue();
        $this->consumir($issued->code);

        $token = PersonalAccessToken::where('tokenable_type', Terminal::class)->sole();

        $this->assertSame(['pdv:terminal:read'], $token->abilities);
        $this->assertSame('pdv-machine', $token->name);
        $this->assertNotNull($token->expires_at);
    }

    public function test_texto_puro_do_pairing_nao_vai_para_banco_nem_log(): void
    {
        Log::spy();
        $issued = $this->issue();

        $result = $this->consumir($issued->code);

        foreach (['info', 'debug', 'warning', 'error', 'critical', 'log'] as $metodo) {
            Log::shouldNotHaveReceived($metodo);
        }
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $result->credential]);

        $despejo = print_r($result, true);
        $this->assertStringContainsString('[REDACTED]', $despejo);
        $this->assertStringNotContainsString($result->credential, $despejo);
    }

    /**
     * Atomicidade: falha na emissão desfaz o provisionamento inteiro.
     *
     * Sem isso o estado possível seria o pior de todos — Terminal ACTIVE,
     * código consumido, nenhuma credencial e nenhum caminho de volta.
     */
    public function test_falha_na_emissao_desfaz_pairing_e_ativacao(): void
    {
        $issued = $this->issue();

        $this->app->bind(IssueTerminalMachineCredential::class, fn () => new class extends IssueTerminalMachineCredential
        {
            public function __construct()
            {
                // Substituto injetado: não precisa das dependências reais.
            }

            public function issue(Terminal $terminal): never
            {
                throw new MachineCredentialRefused('injected-failure');
            }
        });

        $this->assertFailure('credential-failure', fn () => $this->consumir($issued->code));

        $terminal = $this->terminal->fresh();
        $this->assertSame('PENDING', $terminal->status);
        $this->assertNull($terminal->installation_id);

        $row = TerminalPairingCode::findOrFail($issued->pairingId);
        $this->assertNull($row->consumed_at);
        $this->assertNull($row->invalidated_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_falha_ao_persistir_credencial_desfaz_tudo(): void
    {
        $issued = $this->issue();

        // Falha no momento da escrita do PAT, não antes: prova que o rollback
        // alcança a ativação já aplicada dentro da mesma transação.
        PersonalAccessToken::creating(fn () => throw new MachineCredentialRefused('injected-storage'));

        try {
            $this->assertFailure('credential-failure', fn () => $this->consumir($issued->code));
        } finally {
            PersonalAccessToken::clearBootedModels();
        }

        $terminal = $this->terminal->fresh();
        $this->assertSame('PENDING', $terminal->status);
        $this->assertNull($terminal->installation_id);
        $this->assertNull(TerminalPairingCode::findOrFail($issued->pairingId)->consumed_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_replay_apos_pairing_nao_emite_segunda_credencial(): void
    {
        $issued = $this->issue();
        $this->consumir($issued->code);

        // Código de uso único: a segunda tentativa falha e não encosta na
        // credencial já entregue.
        $this->assertFailure('consumed', fn () => $this->consumir($issued->code));

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_pairing_recusado_nao_emite_credencial(): void
    {
        $issued = $this->issue();

        $this->assertFailure('invalid-code', fn () => $this->consumir(
            substr($issued->code, 0, 7).'ZZZZZZZZZZZZ'
        ));

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame('PENDING', $this->terminal->fresh()->status);
    }

    public function test_servicos_de_credencial_estao_disponiveis_para_o_pdv_be_05(): void
    {
        // O PDV-BE-05 vai montar o endpoint em cima destes serviços; o contrato
        // deles é parte da entrega do PDV-BE-04.
        $this->assertInstanceOf(IssueTerminalMachineCredential::class, app(IssueTerminalMachineCredential::class));
        $this->assertInstanceOf(RevokeTerminalMachineCredentials::class, app(RevokeTerminalMachineCredentials::class));
        $this->assertInstanceOf(TerminalAuthenticationEligibility::class, app(TerminalAuthenticationEligibility::class));
    }
}
