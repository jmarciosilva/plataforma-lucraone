<?php

namespace Tests\Feature\Audit;

use Tests\Feature\Tenancy\TenancyTestCase;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Audit\Domain\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuditLogTest extends TenancyTestCase
{
    use RefreshDatabase;

    /**
     * Teste 01: AuditLog é criado com dados corretos.
     */
    public function test_audit_log_created_with_correct_data(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);

        $log = AuditLog::logAction(
            'create',
            'User',
            'user-123',
            ['name' => 'John Doe'],
            'Created new user'
        );

        $this->assertNotNull($log->id);
        $this->assertEquals($this->tenantA->id, $log->tenant_id);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('create', $log->action);
        $this->assertEquals('User', $log->entity_type);
        $this->assertEquals('user-123', $log->entity_id);
    }

    /**
     * Teste 02: AuditLog registra IP e User-Agent.
     */
    public function test_audit_log_captures_ip_and_user_agent(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);

        $log = AuditLog::logAction('update', 'Role', 'role-123');

        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
    }

    /**
     * Teste 03: AuditLog registra request_id.
     */
    public function test_audit_log_captures_request_id(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);

        $log = AuditLog::logAction('delete', 'Permission', 'perm-123');

        $this->assertNotNull($log->request_id);
    }

    /**
     * Teste 04: AuditLog isolado por tenant.
     */
    public function test_audit_logs_isolated_by_tenant(): void
    {
        $userA = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $userB = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantB->id)
            ->create();

        $this->actingAs($userA);
        $logA = AuditLog::logAction('create', 'User', 'user-a');

        $this->actingAs($userB);
        $logB = AuditLog::logAction('create', 'User', 'user-b');

        $this->assertEquals($this->tenantA->id, $logA->tenant_id);
        $this->assertEquals($this->tenantB->id, $logB->tenant_id);

        $tenantALogs = AuditLog::forTenant($this->tenantA->id)->get();
        $this->assertTrue($tenantALogs->contains($logA));
        $this->assertFalse($tenantALogs->contains($logB));
    }

    /**
     * Teste 05: Buscar AuditLogs por user.
     */
    public function test_audit_logs_can_be_filtered_by_user(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);
        AuditLog::logAction('create', 'User', 'user-1');
        AuditLog::logAction('update', 'User', 'user-2');

        $userLogs = AuditLog::forUser($user->id)->get();
        $this->assertCount(2, $userLogs);
        $this->assertTrue($userLogs->every(fn($log) => $log->user_id === $user->id));
    }

    /**
     * Teste 06: Buscar AuditLogs por entity.
     */
    public function test_audit_logs_can_be_filtered_by_entity(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);
        AuditLog::logAction('create', 'Role', 'role-123');
        AuditLog::logAction('update', 'Role', 'role-123');
        AuditLog::logAction('delete', 'Role', 'role-123');

        $roleLogs = AuditLog::forEntity('Role', 'role-123')->get();
        $this->assertCount(3, $roleLogs);
    }

    /**
     * Teste 07: AuditLog sem user autenticado.
     */
    public function test_audit_log_works_without_authenticated_user(): void
    {
        $log = AuditLog::logAction('login', 'User', 'user-123');

        $this->assertNull($log->user_id);
        $this->assertNotNull($log->id);
    }

    /**
     * Teste 08: AuditLog registra changes em JSON.
     */
    public function test_audit_log_stores_changes_as_json(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);

        $changes = [
            'old_values' => ['email' => 'old@example.com'],
            'new_values' => ['email' => 'new@example.com'],
        ];

        $log = AuditLog::logAction('update', 'User', 'user-123', $changes);

        $this->assertEquals($changes, $log->changes);
        $this->assertEquals('old@example.com', $log->changes['old_values']['email']);
    }

    /**
     * Teste 09: Múltiplas ações registradas em sequência.
     */
    public function test_multiple_audit_logs_recorded_in_sequence(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);

        AuditLog::logAction('login', 'User', $user->id, description: 'User logged in');
        AuditLog::logAction('view', 'Role', 'role-1', description: 'Viewed role list');
        AuditLog::logAction('logout', 'User', $user->id, description: 'User logged out');

        $logs = AuditLog::forUser($user->id)->get();
        $this->assertCount(3, $logs);
        $this->assertEquals('login', $logs[0]->action);
        $this->assertEquals('view', $logs[1]->action);
        $this->assertEquals('logout', $logs[2]->action);
    }

    /**
     * Teste 10: AuditLog com description.
     */
    public function test_audit_log_with_description(): void
    {
        $user = User::factory()
            ->active()
            ->forCurrentTenant($this->tenantA->id)
            ->create();

        $this->actingAs($user);

        $description = 'User attempted to access unauthorized resource';
        $log = AuditLog::logAction('access_denied', 'Branch', 'branch-1', description: $description);

        $this->assertEquals($description, $log->description);
    }
}
