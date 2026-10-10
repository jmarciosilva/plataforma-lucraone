<?php

namespace Tests\Feature\Pdv;

/**
 * PDV-BE-05 — GET /api/v1/pdv/health.
 *
 * Contrato do CLIENTE PDV, não diagnóstico interno. O `/api/health` que já
 * existia responde estado de banco e cache para quem opera a plataforma; este
 * responde apenas "você alcançou o backend certo, no contrato certo". A
 * diferença importa: o PDV é um aplicativo distribuído em loja, e o que ele
 * recebe é efetivamente público.
 */
class PdvHealthEndpointTest extends PdvTestCase
{
    public function test_health_responde_200_sem_autenticacao(): void
    {
        $this->getJson('/api/v1/pdv/health')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'status' => 'ok',
                    'api' => 'pdv',
                    'version' => 'v1',
                ],
            ]);
    }

    public function test_health_gera_request_id_quando_ausente(): void
    {
        $resposta = $this->getJson('/api/v1/pdv/health')->assertOk();

        $id = $resposta->headers->get('X-Request-ID');
        $this->assertNotEmpty($id);
        $this->assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $id, 'Deve ser um ULID.');
    }

    public function test_health_preserva_request_id_valido(): void
    {
        $this->withHeader('X-Request-ID', 'pdv-be-05-smoke')
            ->getJson('/api/v1/pdv/health')
            ->assertOk()
            ->assertHeader('X-Request-ID', 'pdv-be-05-smoke');
    }

    public function test_health_nao_vaza_dado_interno(): void
    {
        $corpo = $this->getJson('/api/v1/pdv/health')->assertOk()->getContent();

        // Cada um destes já apareceu em health de projeto por descuido.
        foreach ([
            'testing', 'staging', 'production', 'local',   // APP_ENV
            'mysql', 'sqlite', 'redis', 'database', 'cache',
            'php', 'laravel', 'lucraone-app', 'lucraone-nginx',
            '/app', 'vendor', 'APP_KEY', 'commit', 'hostname',
        ] as $proibido) {
            $this->assertStringNotContainsStringIgnoringCase($proibido, $corpo);
        }
    }

    public function test_health_nao_expoe_checks_do_health_interno(): void
    {
        // O /api/health interno devolve 'checks' com estado de banco e cache.
        // O contrato do PDV não é alias dele.
        $this->getJson('/api/v1/pdv/health')
            ->assertOk()
            ->assertJsonMissingPath('data.checks')
            ->assertJsonMissingPath('data.timestamp');
    }

    public function test_health_interno_continua_intacto(): void
    {
        // Regressão: o contrato antigo não mudou de forma.
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure(['status', 'timestamp', 'version', 'checks' => ['database', 'cache']]);
    }
}
