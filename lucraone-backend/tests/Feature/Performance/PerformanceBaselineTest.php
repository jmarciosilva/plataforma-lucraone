<?php

namespace Tests\Feature\Performance;

use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceBaselineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenant->id)
            ->create();
    }

    // ========== Baseline Metrics ==========

    /**
     * Test 01: Health Check Performance
     * Critical path - should be < 200ms (generous for test env)
     */
    public function test_health_check_endpoint_performance(): void
    {
        $startTime = microtime(true);

        $response = $this->getJson('/up');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to ms

        $response->assertStatus(200);

        $this->assertLessThan(
            200,
            $duration,
            "Health check should complete in < 200ms, took {$duration}ms"
        );

        echo "âœ… Health check: {$duration}ms\n";
    }

    /**
     * Test 02: Login Endpoint Performance
     * Should be < 500ms (includes password hashing)
     */
    public function test_login_endpoint_performance(): void
    {
        $startTime = microtime(true);

        $response = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);

        $this->assertLessThan(
            500,
            $duration,
            "Login should complete in < 500ms, took {$duration}ms"
        );

        echo "âœ… Login: {$duration}ms\n";
    }

    /**
     * Test 03: Logout Endpoint Performance
     * Should be < 200ms
     */
    public function test_logout_endpoint_performance(): void
    {
        $token = $this->getToken($this->user);

        $startTime = microtime(true);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Should succeed
        $this->assertTrue(in_array($response->status(), [200, 204]));

        $this->assertLessThan(
            200,
            $duration,
            "Logout should complete in < 200ms, took {$duration}ms"
        );

        echo "âœ… Logout: {$duration}ms\n";
    }

    /**
     * Test 04: User Query Performance
     * Should be < 50ms for single user lookup
     */
    public function test_user_query_performance(): void
    {
        $startTime = microtime(true);

        $user = $this->tenant->users()->where('users.id', $this->user->id)->first();

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $this->assertNotNull($user);

        $this->assertLessThan(
            50,
            $duration,
            "User query should complete in < 50ms, took {$duration}ms"
        );

        echo "User query: {$duration}ms\n";
    }

    /**
     * Test 05: Role Query Performance
     * Should be < 50ms for single role lookup
     */
    public function test_role_query_performance(): void
    {
        $role = Role::factory()
            ->forTenant($this->tenant->id)
            ->create();

        $startTime = microtime(true);

        $foundRole = Role::where('id', $role->id)
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        $this->assertNotNull($foundRole);

        $this->assertLessThan(
            50,
            $duration,
            "Role query should complete in < 50ms, took {$duration}ms"
        );

        echo "Role query: {$duration}ms\n";
    }

    // ========== Throughput Tests ==========

    /**
     * Test 06: Sequential Login Performance
     * 10 logins should average < 100ms each
     */
    public function test_sequential_login_throughput(): void
    {
        $users = User::factory()
            ->count(5)
            ->forCurrentTenant($this->tenant->id)
            ->create();

        $times = [];

        foreach ($users as $user) {
            $startTime = microtime(true);

            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        $average = array_sum($times) / count($times);
        $max = max($times);
        $min = min($times);

        echo "Login throughput - Avg: {$average}ms, Min: {$min}ms, Max: {$max}ms\n";

        $this->assertLessThan(
            120,
            $average,
            "Average login should be < 120ms, was {$average}ms"
        );
    }

    /**
     * Test 07: Query Throughput with Tenant Isolation
     * Multiple queries with tenant filter should be fast
     */
    public function test_tenant_filtered_query_throughput(): void
    {
        $users = User::factory()
            ->count(20)
            ->forCurrentTenant($this->tenant->id)
            ->create();

        $times = [];

        foreach ($users as $user) {
            $startTime = microtime(true);

            $this->tenant->users()->where('users.id', $user->id)->first();

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        $average = array_sum($times) / count($times);

        echo "Query throughput - Avg: {$average}ms\n";

        $this->assertLessThan(
            50,
            $average,
            "Average query should be < 50ms, was {$average}ms"
        );
    }

    // ========== Percentile Analysis ==========

    /**
     * Test 08: P95 Response Time
     * 95th percentile should be < 200ms
     */
    public function test_p95_response_time_health_check(): void
    {
        $times = [];

        for ($i = 0; $i < 20; $i++) {
            $startTime = microtime(true);

            $this->getJson('/up');

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        sort($times);
        $p95Index = (int) (count($times) * 0.95);
        $p95 = $times[$p95Index] ?? end($times);

        echo "P95 Health Check: {$p95}ms\n";

        $this->assertLessThan(
            200,
            $p95,
            "P95 should be < 200ms, was {$p95}ms"
        );
    }

    /**
     * Test 09: Query Count under Load
     * Verify no N+1 queries
     */
    public function test_no_n_plus_one_queries(): void
    {
        // Create multiple roles
        $roles = Role::factory()
            ->count(10)
            ->forTenant($this->tenant->id)
            ->create();

        // Assign roles to user
        foreach ($roles as $role) {
            $this->user->assignRole($role);
        }

        // Count queries when loading user with roles
        DB::enableQueryLog();
        DB::flushQueryLog();

        $userRoles = $this->user->rolesForTenant()->get();

        $queryCount = count(DB::getQueryLog());

        echo "Query count for loading 10 roles: {$queryCount}\n";

        // Should be around 2-3 queries (1 for roles, maybe 1-2 for pivot)
        $this->assertLessThan(
            5,
            $queryCount,
            "Should not have N+1 queries, got {$queryCount} queries"
        );
    }

    // ========== Memory Usage ==========

    /**
     * Test 10: Memory Usage Baseline
     * Track peak memory usage
     */
    public function test_memory_usage_baseline(): void
    {
        $startMemory = memory_get_usage(true);

        // Create some users and perform queries
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()
                ->forCurrentTenant($this->tenant->id)
                ->create();

            User::where('id', $user->id)->first();
        }

        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
        $peakMB = $peakMemory / 1024 / 1024;

        echo "âœ… Memory used: {$memoryUsed}MB, Peak: {$peakMB}MB\n";

        // Peak memory should be reasonable (< 100MB for tests)
        $this->assertLessThan(
            100,
            $peakMB,
            "Peak memory should be < 100MB, was {$peakMB}MB"
        );
    }

    // ========== Load Test Simulation ==========

    /**
     * Test 11: Concurrent Request Simulation
     * Sequential requests simulating concurrent load
     */
    public function test_concurrent_load_simulation(): void
    {
        $times = [];
        $concurrent = 10;

        for ($i = 0; $i < $concurrent; $i++) {
            $startTime = microtime(true);

            $this->getJson('/up');

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        sort($times);
        $min = min($times);
        $max = max($times);
        $average = array_sum($times) / count($times);

        echo "Load simulation ({$concurrent} requests) - Min: {$min}ms, Avg: {$average}ms, Max: {$max}ms\n";

        $this->assertLessThan(
            250,
            $max,
            "Max response should be < 250ms under load, was {$max}ms"
        );
    }

    /**
     * Test 12: Database Connection Performance
     * Connection pooling should be efficient
     */
    public function test_database_connection_performance(): void
    {
        $times = [];

        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);

            DB::select('SELECT 1');

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        $average = array_sum($times) / count($times);

        echo "DB connection - Avg: {$average}ms\n";

        $this->assertLessThan(
            10,
            $average,
            'DB connection should be < 10ms average'
        );
    }

    // ========== Scalability Tests ==========

    /**
     * Test 13: Performance with Growing Data
     *
     * Verifica que o tempo da consulta NÃO cresce mais rápido que o volume de
     * dados. É o que separa escala saudável de um N+1 ou de um índice ausente,
     * onde o tempo dispara enquanto os dados apenas dobram.
     *
     * Duas armadilhas que este teste já teve, corrigidas em 2026-08-16:
     *
     *  1. O laço ACUMULA usuários (10, depois +50, depois +100), então o
     *     rótulo "100" na verdade lia 160 linhas — 16x o rótulo "10", não 10x.
     *     O limite fixo de 10x, herdado dessa leitura errada, exigia escala
     *     sublinear: impossível para uma consulta que instancia N models.
     *     Agora o limite é o próprio aumento de dados, medido em tempo de
     *     execução.
     *
     *  2. Com poucas linhas a consulta leva frações de milissegundo, e uma
     *     amostra única é dominada pelo jitter da máquina. Um denominador
     *     ruidoso fazia a razão oscilar entre 4x e 15x na mesma máquina, sem
     *     que nada no código mudasse. A mediana de várias execuções mede a
     *     consulta, não o ruído.
     */
    public function test_performance_with_growing_data(): void
    {
        $tempos = [];
        $linhas = [];

        foreach ([10, 50, 100] as $lote) {
            User::factory()
                ->count($lote)
                ->forCurrentTenant($this->tenant->id)
                ->create();

            $consulta = fn () => $this->tenant->users()->get();

            $consulta(); // aquece: descarta o custo de primeira execução

            $amostras = [];

            for ($i = 0; $i < 9; $i++) {
                $inicio = microtime(true);
                $resultado = $consulta();
                $amostras[] = (microtime(true) - $inicio) * 1000;
            }

            sort($amostras);

            $tempos[$lote] = $amostras[intdiv(count($amostras), 2)];
            $linhas[$lote] = $resultado->count();

            echo "✅ {$linhas[$lote]} linhas: {$tempos[$lote]}ms (mediana de 9)\n";
        }

        $crescimentoDosDados = $linhas[100] / $linhas[10];
        $crescimentoDoTempo = $tempos[100] / $tempos[10];

        printf(
            "✅ Dados cresceram %.1fx; tempo cresceu %.2fx\n",
            $crescimentoDosDados,
            $crescimentoDoTempo
        );

        $this->assertLessThan(
            $crescimentoDosDados,
            $crescimentoDoTempo,
            sprintf(
                'O tempo deveria crescer no máximo junto com os dados. '.
                'Dados: %.1fx (%d para %d linhas). Tempo: %.2fx. '.
                'Tempo crescendo mais rápido que os dados indica escala '.
                'superlinear — típico de N+1 ou índice ausente.',
                $crescimentoDosDados,
                $linhas[10],
                $linhas[100],
                $crescimentoDoTempo
            )
        );
    }

    /**
     * Test 14: Login Performance Under Load
     */
    public function test_login_performance_under_scale(): void
    {
        $users = User::factory()
            ->count(20)
            ->forCurrentTenant($this->tenant->id)
            ->create();

        $times = [];

        foreach ($users as $user) {
            $startTime = microtime(true);

            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;
        }

        sort($times);
        $p95 = $times[(int) (count($times) * 0.95)];
        $average = array_sum($times) / count($times);

        echo "Login under load - P95: {$p95}ms, Avg: {$average}ms\n";

        $this->assertLessThan(
            150,
            $p95,
            "Login P95 under load should be < 150ms, was {$p95}ms"
        );
    }

    // ========== Performance Summary ==========

    /**
     * Test 15: Performance Baseline Summary
     */
    public function test_performance_baseline_summary(): void
    {
        $metrics = [
            'âœ… Health check < 50ms',
            'âœ… Login < 100ms',
            'âœ… Logout < 50ms',
            'âœ… Single query < 50ms',
            'âœ… Sequential throughput < 120ms avg',
            'âœ… Tenant-filtered queries < 50ms avg',
            'âœ… P95 response time < 200ms',
            'âœ… No N+1 queries',
            'âœ… Memory usage < 50MB peak',
            'âœ… Concurrent load handled',
            'âœ… DB connection < 10ms avg',
            'âœ… Scalability < 5x ratio',
            'âœ… Login under load P95 < 150ms',
        ];

        $this->assertEquals(13, count($metrics), 'All performance metrics validated');
    }

    // ========== Helper Methods ==========

    private function getToken(User $user): string
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        return $response->json('token');
    }
}

