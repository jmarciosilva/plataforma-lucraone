<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Identity\Domain\TokenAbility;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Tests\Feature\Security\ApiAuthorizationTestCase;

class RequestCorrelationTest extends ApiAuthorizationTestCase
{
    public function test_health_without_header_generates_ulid(): void
    {
        $response = $this->getJson('/api/health')->assertOk()->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUlid($response->headers->get('X-Request-ID')));
    }

    public function test_received_id_is_preserved(): void
    {
        $this->withHeader('X-Request-ID', 'pdv-be-01-smoke')->getJson('/api/health')
            ->assertOk()->assertHeader('X-Request-ID', 'pdv-be-01-smoke');
    }

    public function test_two_requests_generate_distinct_ids(): void
    {
        $first = $this->getJson('/api/health')->headers->get('X-Request-ID');
        $second = $this->getJson('/api/health')->headers->get('X-Request-ID');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first, $second);
    }

    public function test_login_receives_correlation_on_validation_error(): void
    {
        $this->withHeader('X-Request-ID', 'login-validation')->postJson('/api/auth/login', [])
            ->assertUnprocessable()->assertHeader('X-Request-ID', 'login-validation');
    }

    public function test_successful_login_receives_correlation(): void
    {
        $response = $this->postJson('/api/auth/login', ['email' => $this->gerente->email, 'password' => 'password'])
            ->assertOk()->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUlid($response->headers->get('X-Request-ID')));
    }

    public function test_authenticated_business_api_receives_correlation(): void
    {
        $this->comoGerente()->withHeader('X-Request-ID', 'authenticated-request')->getJson('/api/v1/products')
            ->assertOk()->assertHeader('X-Request-ID', 'authenticated-request');
    }

    public function test_unauthenticated_response_receives_correlation(): void
    {
        $response = $this->getJson('/api/v1/products')->assertUnauthorized()->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUlid($response->headers->get('X-Request-ID')));
    }

    public function test_policy_forbidden_response_receives_correlation(): void
    {
        $this->comoCreateRole()->withHeader('X-Request-ID', 'policy-denied')->getJson('/api/v1/products')
            ->assertForbidden()->assertHeader('X-Request-ID', 'policy-denied');
    }

    public function test_tenant_failure_receives_correlation(): void
    {
        $this->comoGerente()->withHeader('X-Tenant-ID', $this->outroTenant->id)
            ->withHeader('X-Request-ID', 'tenant-denied')->getJson('/api/v1/products')
            ->assertForbidden()->assertHeader('X-Request-ID', 'tenant-denied');
    }

    public function test_ability_failure_receives_correlation(): void
    {
        $token = $this->gerente->createToken('write-only', [TokenAbility::ESCRITA])->plainTextToken;
        $this->withToken($token)->withHeader('X-Tenant-ID', $this->tenant->id)
            ->withHeader('X-Request-ID', 'ability-denied')->getJson('/api/v1/products')
            ->assertForbidden()->assertHeader('X-Request-ID', 'ability-denied');
    }

    public function test_missing_business_entity_receives_correlation(): void
    {
        $this->comoGerente()->withHeader('X-Request-ID', 'missing-product')->getJson('/api/v1/products/'.Str::ulid())
            ->assertNotFound()->assertHeader('X-Request-ID', 'missing-product');
    }

    public function test_oversized_header_is_replaced_with_ulid(): void
    {
        $response = $this->withHeader('X-Request-ID', str_repeat('a', 129))->getJson('/api/health')->assertOk();
        $this->assertTrue(Str::isUlid($response->headers->get('X-Request-ID')));
    }

    public function test_unsafe_header_is_replaced_with_ulid(): void
    {
        $response = $this->withHeader('X-Request-ID', 'invalid value')->getJson('/api/health')->assertOk();
        $this->assertTrue(Str::isUlid($response->headers->get('X-Request-ID')));
    }

    public function test_request_log_and_audit_share_id_without_leaking_to_next_request(): void
    {
        $handler = new TestHandler;
        $logger = Log::getLogger();
        $logger->pushHandler($handler);
        Route::middleware(['api', 'auth:sanctum', 'token.ability', 'tenant'])->get('/api/correlation-probe', function () {
            Log::info('correlation-probe');
            $audit = AuditLog::logAction('correlation-probe', 'test');

            return response()->json(['request_id' => request()->header('X-Request-ID'), 'audit_id' => $audit->request_id]);
        });
        try {
            $this->comoGerente()->withHeader('X-Request-ID', 'shared-correlation')->getJson('/api/correlation-probe')
                ->assertOk()->assertHeader('X-Request-ID', 'shared-correlation')
                ->assertJsonPath('request_id', 'shared-correlation')->assertJsonPath('audit_id', 'shared-correlation');
            $this->assertSame('shared-correlation', $handler->getRecords()[0]->context['request_id']);
            $this->flushHeaders();
            $response = $this->comoGerente()->getJson('/api/correlation-probe')->assertOk();
            $id = $response->headers->get('X-Request-ID');
            $this->assertTrue(Str::isUlid($id));
            $this->assertNotSame('shared-correlation', $id);
            $this->assertSame($id, $handler->getRecords()[1]->context['request_id']);
            $response->assertJsonPath('audit_id', $id);
            Log::info('outside-request');
            $this->assertArrayNotHasKey('request_id', $handler->getRecords()[2]->context);
        } finally {
            $logger->popHandler();
        }
    }
}
