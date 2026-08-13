<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: Health check endpoint retorna JSON.
     */
    public function test_health_check_returns_json(): void
    {
        $response = $this->getJson('/up');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'healthy',
        ]);
    }

    /**
     * Teste 02: Health check retorna timestamp.
     */
    public function test_health_check_includes_timestamp(): void
    {
        $response = $this->getJson('/up');

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'version',
        ]);
    }

    /**
     * Teste 03: Health check retorna checks do database.
     */
    public function test_health_check_includes_database_check(): void
    {
        $response = $this->getJson('/up');

        $response->assertJsonStructure([
            'checks' => [
                'database',
            ],
        ]);
    }

    /**
     * Teste 04: Health check retorna checks do cache.
     */
    public function test_health_check_includes_cache_check(): void
    {
        $response = $this->getJson('/up');

        $response->assertJsonStructure([
            'checks' => [
                'cache',
            ],
        ]);
    }

    /**
     * Teste 05: Health database check passa quando DB conecta.
     */
    public function test_health_database_check_passes(): void
    {
        $response = $this->getJson('/up');

        $this->assertEquals('healthy', $response->json('checks.database.status'));
    }
}
