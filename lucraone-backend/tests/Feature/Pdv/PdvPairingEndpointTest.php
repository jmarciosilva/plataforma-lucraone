<?php

namespace Tests\Feature\Pdv;

use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PDV-BE-05 — POST /api/v1/pdv/terminals/pair.
 *
 * Endpoint público por necessidade: antes do pairing o Terminal não tem
 * credencial nenhuma, então não há o que autenticar. O que protege não é
 * autenticação, é a combinação de código curto com prazo, uso único, attempts
 * persistentes (PDV-BE-03) e rate limiting nas duas dimensões (aqui).
 */
class PdvPairingEndpointTest extends PdvTestCase
{
    public function test_pairing_valido_ativa_terminal_e_devolve_credencial(): void
    {
        $emitido = $this->emitirPairing();
        $uuid = (string) Str::uuid();

        $resposta = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => $uuid,
        ])->assertOk();

        $terminal = $this->terminal->fresh();

        $this->assertSame('ACTIVE', $terminal->status);
        $this->assertSame($uuid, $terminal->installation_id);
        $this->assertNotNull(TerminalPairingCode::findOrFail($emitido->pairingId)->consumed_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $resposta->assertJsonPath('data.terminal.id', $terminal->id)
            ->assertJsonPath('data.terminal.name', 'Caixa 001')
            ->assertJsonPath('data.terminal.status', 'ACTIVE')
            ->assertJsonPath('data.terminal.installation_id', $uuid)
            ->assertJsonPath('data.tenant.id', $terminal->tenant_id)
            ->assertJsonPath('data.company.id', $terminal->company_id)
            ->assertJsonPath('data.branch.id', $terminal->branch_id)
            ->assertJsonPath('data.credential.token_type', 'Bearer');

        $this->assertNotEmpty($resposta->json('data.credential.access_token'));
        $this->assertNotEmpty($resposta->json('data.credential.expires_at'));
    }

    public function test_pairing_devolve_exatamente_o_contrato_acordado(): void
    {
        $emitido = $this->emitirPairing();

        $resposta = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk();

        // Estrutura fechada: um atributo novo no model não pode virar campo
        // público sem decisão. É o que o mapeamento manual protege.
        $resposta->assertJsonStructure([
            'data' => [
                'terminal' => ['id', 'name', 'status', 'installation_id'],
                'tenant' => ['id', 'name'],
                'company' => ['id', 'trade_name'],
                'branch' => ['id', 'name', 'code'],
                'credential' => ['token_type', 'access_token', 'expires_at'],
            ],
        ]);

        $dados = $resposta->json('data');
        $this->assertSame(['terminal', 'tenant', 'company', 'branch', 'credential'], array_keys($dados));
        $this->assertSame(['id', 'name', 'status', 'installation_id'], array_keys($dados['terminal']));
        $this->assertSame(['id', 'name'], array_keys($dados['tenant']));
        $this->assertSame(['id', 'trade_name'], array_keys($dados['company']));
        $this->assertSame(['id', 'name', 'code'], array_keys($dados['branch']));
        $this->assertSame(['token_type', 'access_token', 'expires_at'], array_keys($dados['credential']));
    }

    public function test_pairing_nao_vaza_interno_do_pairing_nem_do_token(): void
    {
        $emitido = $this->emitirPairing();

        $corpo = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk()->getContent();

        foreach ([
            'code_hash', 'selector', 'attempts', 'consumed_at', 'invalidated_at',
            'tokenable', 'abilities', 'last_used_at', 'password', 'permissions',
            'memberships', 'document', 'legal_name',
        ] as $proibido) {
            $this->assertStringNotContainsString($proibido, $corpo);
        }

        // O segredo do pairing enviado não volta na resposta.
        $this->assertStringNotContainsString($emitido->code, $corpo);
    }

    public function test_resposta_com_credencial_proibe_cache(): void
    {
        $emitido = $this->emitirPairing();

        $resposta = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk();

        $this->assertStringContainsString('no-store', $resposta->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $resposta->headers->get('Cache-Control'));
    }

    public function test_expires_at_e_iso8601_com_timezone(): void
    {
        $emitido = $this->emitirPairing();

        $expira = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk()->json('data.credential.expires_at');

        $this->assertMatchesRegularExpression(
            '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\+|-)\d{2}:\d{2}\z/',
            $expira
        );
    }

    public function test_replay_do_mesmo_codigo_e_recusado_e_nao_emite_segunda_credencial(): void
    {
        $emitido = $this->emitirPairing();
        $uuid = (string) Str::uuid();

        $this->postPair(['pairing_code' => $emitido->code, 'installation_id' => $uuid])->assertOk();
        $terminalDepois = $this->terminal->fresh();

        // Código de uso único: não é idempotente e não devolve a credencial
        // anterior. Quem perder a resposta precisa de novo pairing.
        $segunda = $this->postPair(['pairing_code' => $emitido->code, 'installation_id' => (string) Str::uuid()]);

        $this->assertErroPdv($segunda, 422, 'pairing_failed');
        $this->assertNull($segunda->json('data'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame($terminalDepois->installation_id, $this->terminal->fresh()->installation_id);
        $this->assertSame('ACTIVE', $this->terminal->fresh()->status);
    }

    /**
     * Falhas de pairing são publicamente indistinguíveis.
     *
     * O domínio sabe exatamente o motivo — expirado, consumido, invalidado,
     * segredo errado, selector inexistente, Terminal inadequado — e nada disso
     * pode sair pela API: a diferença entre "selector não existe" e "segredo
     * errado" diria ao atacante que ele acertou metade do código.
     */
    #[DataProvider('falhasDePairing')]
    public function test_falhas_de_pairing_sao_indistinguiveis(string $caso): void
    {
        $emitido = $this->emitirPairing();
        $codigo = $emitido->code;

        switch ($caso) {
            case 'selector-inexistente':
                $codigo = 'ZZZZZZ.'.substr($emitido->code, 7);
                break;
            case 'segredo-errado':
                $codigo = substr($emitido->code, 0, 7).'ZZZZZZZZZZZZ';
                break;
            case 'expirado':
                $this->travel(11)->minutes();
                break;
            case 'consumido':
                $this->postPair(['pairing_code' => $codigo, 'installation_id' => (string) Str::uuid()])->assertOk();
                break;
            case 'invalidado':
                $this->emitirPairing(); // regenerar invalida o anterior
                break;
            case 'terminal-bloqueado':
                $this->terminal->fresh()->update(['status' => Terminal::STATUS_BLOCKED]);
                break;
        }

        $resposta = $this->postPair(['pairing_code' => $codigo, 'installation_id' => (string) Str::uuid()]);

        $this->assertErroPdv($resposta, 422, 'pairing_failed');

        // Mesma mensagem para todos: nenhum detalhe do motivo interno.
        $this->assertSame('Não foi possível concluir o pareamento.', $resposta->json('error.message'));
        $this->assertNull($resposta->json('error.reason'));
        $this->assertNull($resposta->json('error.errors'));
    }

    public static function falhasDePairing(): array
    {
        return [
            'selector inexistente' => ['selector-inexistente'],
            'segredo errado' => ['segredo-errado'],
            'expirado' => ['expirado'],
            'consumido' => ['consumido'],
            'invalidado' => ['invalidado'],
            'terminal bloqueado' => ['terminal-bloqueado'],
        ];
    }

    #[DataProvider('payloadsInvalidos')]
    public function test_payload_invalido_responde_validation_error(array $payload): void
    {
        $resposta = $this->postPair($payload);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame('PENDING', $this->terminal->fresh()->status);
    }

    public static function payloadsInvalidos(): array
    {
        return [
            'body vazio' => [[]],
            'sem pairing_code' => [['installation_id' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301']],
            'sem installation_id' => [['pairing_code' => 'AAAAAA.BBBBBBBBBBBB']],
            'installation_id malformado' => [['pairing_code' => 'AAAAAA.BBBBBBBBBBBB', 'installation_id' => 'nao-e-uuid']],
            'installation_id uuid v1' => [['pairing_code' => 'AAAAAA.BBBBBBBBBBBB', 'installation_id' => '2c1b2fb0-a5a3-11ee-be56-0242ac120002']],
            'pairing_code nao string' => [['pairing_code' => ['a'], 'installation_id' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301']],
            'pairing_code gigante' => [['pairing_code' => 'A', 'installation_id' => '3f2504e0-4f89-41d3-9a0c-0305e82c3301']],
        ];
    }

    public function test_campo_gigante_e_recusado_sem_ecoar_conteudo(): void
    {
        $gigante = str_repeat('A', 5000);

        $resposta = $this->postPair([
            'pairing_code' => $gigante,
            'installation_id' => (string) Str::uuid(),
        ]);

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertStringNotContainsString($gigante, $resposta->getContent());
    }

    public function test_erro_de_validacao_nao_ecoa_o_codigo_enviado(): void
    {
        $emitido = $this->emitirPairing();

        $resposta = $this->postPair(['pairing_code' => $emitido->code]); // falta installation_id

        $this->assertErroPdv($resposta, 422, 'validation_error');
        $this->assertStringNotContainsString($emitido->code, $resposta->getContent());
    }

    public function test_pairing_nao_registra_segredo_nem_token_em_log(): void
    {
        Log::spy();
        $emitido = $this->emitirPairing();

        $resposta = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk();

        foreach (['info', 'debug', 'warning', 'error', 'critical', 'log'] as $metodo) {
            Log::shouldNotHaveReceived($metodo);
        }
        $this->assertDatabaseCount('audit_logs', 0);

        // E o token entregue não foi persistido em claro.
        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => $resposta->json('data.credential.access_token'),
        ]);
    }

    public function test_pairing_recusado_nao_cria_credencial(): void
    {
        $this->emitirPairing();

        $this->postPair([
            'pairing_code' => 'ZZZZZZ.ZZZZZZZZZZZZ',
            'installation_id' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_pairing_preserva_request_id_enviado(): void
    {
        $emitido = $this->emitirPairing();

        $this->postPair(
            ['pairing_code' => $emitido->code, 'installation_id' => (string) Str::uuid()],
            ['X-Request-ID' => 'pdv-pair-001']
        )->assertOk()->assertHeader('X-Request-ID', 'pdv-pair-001');
    }

    public function test_attempts_persistentes_continuam_valendo(): void
    {
        // Rate limit não substitui attempts: o controle do PDV-BE-03 segue
        // marcando a tentativa errada no próprio código.
        $emitido = $this->emitirPairing();

        $this->postPair([
            'pairing_code' => substr($emitido->code, 0, 7).'ZZZZZZZZZZZZ',
            'installation_id' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->assertSame(1, TerminalPairingCode::findOrFail($emitido->pairingId)->attempts);
    }
}
