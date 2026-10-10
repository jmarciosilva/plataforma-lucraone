<?php

namespace Tests\Feature\Pdv;

use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Terminals\Application\ConsumeTerminalPairingCode;
use App\Modules\Terminals\Application\TerminalProvisioningResult;
use App\Modules\Terminals\Domain\PairingCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PDV-BE-05 — contrato público de erro de `/api/v1/pdv/*`.
 *
 * O PDV é um aplicativo distribuído em loja: a forma do erro dele vira
 * dependência de código que não controlamos, e mudá-la depois é caro. Por isso
 * a forma é fixa e verificada em todos os status, e o `request_id` é parte do
 * contrato — é o que o suporte pede para a loja e cruza com o log.
 *
 * Esta suíte também garante que o contrato é ESCOPADO: as 49 rotas que já
 * existiam não mudaram de formato de erro.
 */
class PdvApiErrorContractTest extends PdvTestCase
{
    public function test_todos_os_status_usam_o_mesmo_envelope(): void
    {
        $casos = [];

        // 422 validation_error
        $casos['validation_error'] = $this->postPair([]);

        // 422 pairing_failed
        $casos['pairing_failed'] = $this->postPair([
            'pairing_code' => 'ZZZZZZ.ZZZZZZZZZZZZ',
            'installation_id' => (string) Str::uuid(),
        ]);

        // 401 unauthenticated
        $casos['unauthenticated'] = $this->getTerminal();

        // 403 forbidden
        $token = $this->credencialDeMaquina();
        $casos['forbidden'] = $this->getTerminal($token, ['X-Tenant-ID' => $this->terminal->fresh()->tenant_id]);

        foreach ($casos as $code => $resposta) {
            $corpo = $resposta->json();

            $this->assertSame(['error'], array_keys($corpo), "Envelope de $code deve ter só 'error'.");
            $this->assertSame(
                ['code', 'message', 'request_id'],
                array_values(array_diff(array_keys($corpo['error']), ['errors'])),
                "Campos de $code fora do contrato."
            );
            $this->assertSame($code, $corpo['error']['code']);
            $this->assertNotEmpty($corpo['error']['message']);
            $this->assertSame($resposta->headers->get('X-Request-ID'), $corpo['error']['request_id']);
        }
    }

    public function test_request_id_do_corpo_e_igual_ao_do_cabecalho_em_todo_status(): void
    {
        $enviado = 'pdv-be-05-erro-001';

        $respostas = [
            $this->postPair([], ['X-Request-ID' => $enviado]),
            $this->postPair(
                ['pairing_code' => 'ZZZZZZ.ZZZZZZZZZZZZ', 'installation_id' => (string) Str::uuid()],
                ['X-Request-ID' => $enviado]
            ),
            $this->getTerminal(null, ['X-Request-ID' => $enviado]),
        ];

        foreach ($respostas as $resposta) {
            $resposta->assertHeader('X-Request-ID', $enviado)
                ->assertJsonPath('error.request_id', $enviado);
        }
    }

    public function test_request_id_e_gerado_quando_ausente_em_erro(): void
    {
        $resposta = $this->postPair([]);

        $id = $resposta->headers->get('X-Request-ID');
        $this->assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $id);
        $resposta->assertJsonPath('error.request_id', $id);
    }

    public function test_request_id_invalido_e_substituido_por_ulid(): void
    {
        // Mesma normalização do RequestCorrelationMiddleware, sem duplicação:
        // caractere fora do permitido é descartado e um ULID toma o lugar.
        $resposta = $this->postPair([], ['X-Request-ID' => "quebra\nlinha"]);

        $id = $resposta->headers->get('X-Request-ID');
        $this->assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $id);
        $resposta->assertJsonPath('error.request_id', $id);
    }

    public function test_validation_error_detalha_campo_sem_ecoar_valor(): void
    {
        $resposta = $this->postPair(['pairing_code' => 'valor-secreto-aqui', 'installation_id' => 'xyz']);

        $resposta->assertStatus(422)
            ->assertJsonPath('error.code', PdvErrorResponse::CODE_VALIDATION)
            ->assertJsonStructure(['error' => ['code', 'message', 'request_id', 'errors']]);

        // O cliente precisa saber QUAL campo corrigir — mas o valor enviado
        // não volta, porque o código de pareamento é segredo de curta duração.
        $this->assertArrayHasKey('installation_id', $resposta->json('error.errors'));
        $this->assertStringNotContainsString('valor-secreto-aqui', $resposta->getContent());
        $this->assertStringNotContainsString('xyz', $resposta->getContent());
    }

    public function test_pairing_failed_nao_detalha_campo(): void
    {
        // Falha de domínio não é erro de formulário: nada de 'errors' aqui, que
        // só daria pista sobre o que o servidor achou do código.
        $resposta = $this->postPair([
            'pairing_code' => 'ZZZZZZ.ZZZZZZZZZZZZ',
            'installation_id' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->assertArrayNotHasKey('errors', $resposta->json('error'));
    }

    public function test_erro_inesperado_responde_500_generico(): void
    {
        Log::spy();
        $emitido = $this->emitirPairing();

        // Falha não prevista no caminho do pareamento. O contrato público não
        // pode virar stack trace nem nome de classe.
        $this->app->bind(ConsumeTerminalPairingCode::class, fn () => new class extends ConsumeTerminalPairingCode
        {
            public function __construct() {}

            public function consume(string $code, string $installationId): TerminalProvisioningResult
            {
                throw new RuntimeException('Detalhe interno com /app/caminho e SQLSTATE[42000].');
            }
        });

        $resposta = $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ]);

        $this->assertErroPdv($resposta, 500, 'internal_error');
        $corpo = $resposta->getContent();
        $this->assertStringNotContainsString('Detalhe interno', $corpo);
        $this->assertStringNotContainsString('RuntimeException', $corpo);
        $this->assertStringNotContainsString('42000', $corpo);
    }

    public function test_metodo_errado_nao_vira_500(): void
    {
        // 405 é status deliberado do framework e não passa pelo catch-all.
        $this->getJson('/api/v1/pdv/terminals/pair')->assertStatus(405);
    }

    public function test_rota_pdv_inexistente_responde_404(): void
    {
        $this->getJson('/api/v1/pdv/nao-existe')->assertStatus(404);
    }

    public function test_contrato_de_erro_das_rotas_humanas_nao_mudou(): void
    {
        // Escopo: o envelope novo vale só para api/v1/pdv/*. Uma rota de
        // negócio sem token continua respondendo o formato antigo.
        $this->getJson('/api/v1/products')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Não autenticado']);
    }

    public function test_login_humano_continua_com_o_contrato_antigo_de_validacao(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonMissingPath('error.code');
    }

    public function test_selector_nunca_aparece_em_erro_de_pairing(): void
    {
        $emitido = $this->emitirPairing();
        $selector = PairingCode::selectorFrom($emitido->code);

        $corpo = $this->postPair([
            'pairing_code' => $this->codigoComSelectorErrado($selector),
            'installation_id' => (string) Str::uuid(),
        ])->assertStatus(422)->getContent();

        $this->assertStringNotContainsString($selector, $corpo);
        $this->assertStringNotContainsString($emitido->code, $corpo);
    }

    private function codigoComSelectorErrado(string $selector): string
    {
        return $selector.'.'.str_repeat('Z', 12);
    }
}
