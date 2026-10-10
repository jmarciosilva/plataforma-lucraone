<?php

namespace Tests\Feature\Terminals;

use App\Modules\Identity\Domain\TokenAbility;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * PDV-BE-04 — propriedades da credencial de máquina emitida.
 *
 * O que se prova aqui é o alcance e a forma da credencial: ability exata, sem
 * coringa, sem herdar o espaço humano, com prazo explícito, e com o texto puro
 * saindo uma única vez sem passar por banco nem por log.
 */
class TerminalMachineCredentialTest extends MachineTestCase
{
    private function tokenPersistido(): PersonalAccessToken
    {
        return PersonalAccessToken::where('tokenable_type', Terminal::class)
            ->where('tokenable_id', $this->terminal->id)
            ->sole();
    }

    public function test_terminal_ativo_emite_credencial(): void
    {
        $credencial = $this->emitir();

        $this->assertSame($this->terminal->id, $credencial->terminalId);
        $this->assertNotSame('', $credencial->plainTextToken);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame('pdv-machine', $this->tokenPersistido()->name);
    }

    public function test_ability_e_exatamente_a_de_leitura_do_proprio_terminal(): void
    {
        $this->emitir();

        $this->assertSame(['pdv:terminal:read'], $this->tokenPersistido()->abilities);
        $this->assertSame(['pdv:terminal:read'], MachineTokenAbility::paraCredencialDeMaquina());
    }

    public function test_credencial_nao_recebe_coringa(): void
    {
        $this->emitir();
        $token = $this->tokenPersistido();

        $this->assertNotContains('*', $token->abilities);

        // `can('*')` do Sanctum é true só quando a lista contém o coringa; a
        // asserção importante é a de baixo: nenhuma ability inventada passa.
        $this->assertFalse($token->can('pdv:terminal:write'));
        $this->assertFalse($token->can('sales:write'));
    }

    public function test_credencial_nao_recebe_abilities_humanas(): void
    {
        $this->emitir();
        $token = $this->tokenPersistido();

        $this->assertFalse($token->can(TokenAbility::LEITURA));
        $this->assertFalse($token->can(TokenAbility::ESCRITA));
        $this->assertNotContains(TokenAbility::LEITURA, $token->abilities);
        $this->assertNotContains(TokenAbility::ESCRITA, $token->abilities);
    }

    public function test_ability_de_maquina_nao_vive_no_token_ability_humano(): void
    {
        // Fontes separadas: acrescentar uma ability de PDV não pode ampliar,
        // por descuido, o alcance de um token de pessoa.
        $this->assertNotContains(
            MachineTokenAbility::TERMINAL_READ,
            TokenAbility::paraSessaoHumana()
        );
    }

    public function test_texto_puro_sai_uma_vez_e_nao_e_persistido(): void
    {
        $credencial = $this->emitir();
        $token = $this->tokenPersistido();

        // O banco guarda o SHA-256, nunca o valor legível.
        [, $segredo] = explode('|', $credencial->plainTextToken, 2);
        $this->assertSame(hash('sha256', $segredo), $token->token);
        $this->assertNotSame($credencial->plainTextToken, $token->token);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $credencial->plainTextToken]);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $segredo]);

        // E não há como relê-lo: o objeto é a única entrega.
        $this->assertNull($token->getAttribute('plain_text_token'));
    }

    public function test_debug_do_objeto_censura_o_segredo(): void
    {
        $credencial = $this->emitir();

        $despejo = print_r($credencial, true);

        $this->assertStringContainsString('[REDACTED]', $despejo);
        $this->assertStringNotContainsString($credencial->plainTextToken, $despejo);
    }

    public function test_emissao_nao_registra_segredo_em_log_nem_auditoria(): void
    {
        Log::spy();

        $this->emitir();

        foreach (['info', 'debug', 'warning', 'error', 'critical', 'log'] as $metodo) {
            Log::shouldNotHaveReceived($metodo);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_expiracao_e_explicita_e_respeita_o_teto_do_sanctum(): void
    {
        $credencial = $this->emitir();
        $token = $this->tokenPersistido();

        $this->assertNotNull($token->expires_at);

        $ttl = (int) config('pdv.machine_credentials.ttl_minutes');
        $this->assertSame(720, $ttl);
        $this->assertSame(
            $token->created_at->addMinutes($ttl)->timestamp,
            $token->expires_at->timestamp
        );
        $this->assertSame($token->expires_at->timestamp, $credencial->expiresAt->timestamp);

        // O teto global do Sanctum é medido sobre created_at e vale para
        // qualquer tokenable: declarar validade acima dele seria ficção.
        $this->assertLessThanOrEqual((int) config('sanctum.expiration'), $ttl);
    }

    public function test_terminal_pendente_nao_recebe_credencial(): void
    {
        $pendente = Terminal::factory()->create();
        $pendente->tenant->update(['status' => 'ACTIVE', 'active' => true]);

        $this->assertEmissaoRecusada('terminal-not-authenticable', fn () => $this->emitir($pendente));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_estrutura_nao_operacional_nao_recebe_credencial(): void
    {
        $this->mudarEstrutura('company', 'SUSPENDED');

        $this->assertEmissaoRecusada('terminal-not-authenticable', fn () => $this->emitir());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
