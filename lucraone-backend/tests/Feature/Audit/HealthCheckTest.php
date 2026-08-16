<?php

namespace Tests\Feature\Audit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
            'status' => 'up',
        ]);
    }

    /**
     * Teste 02: Health check endpoint is accessible.
     */
    public function test_health_check_is_accessible(): void
    {
        $response = $this->getJson('/up');

        $response->assertStatus(200);
    }

    /**
     * Teste 03: Health check return status field.
     */
    public function test_health_check_returns_status_field(): void
    {
        $response = $this->getJson('/up');

        $response->assertJsonStructure([
            'status',
        ]);
    }
}
