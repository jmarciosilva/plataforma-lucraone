<?php

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Domain\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StructuredLoggingService
{
    private array $context = [];

    public function __construct()
    {
        $this->enrichContext();
    }

    private function enrichContext(): void
    {
        $this->context = [
            'request_id' => request()->header('X-Request-ID') ?? Str::ulid(),
            'user_id' => auth()->user()?->id,
            'tenant_id' => auth()->user()?->tenant_id ?? (app('TenantContext')->getTenantId()),
            'ip_address' => request()->ip(),
            'path' => request()->path(),
            'method' => request()->method(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function info(string $message, array $data = []): void
    {
        $this->log('info', $message, $data);
    }

    public function warning(string $message, array $data = []): void
    {
        $this->log('warning', $message, $data);
    }

    public function error(string $message, array $data = []): void
    {
        $this->log('error', $message, $data);
    }

    public function debug(string $message, array $data = []): void
    {
        $this->log('debug', $message, $data);
    }

    public function auditLog(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $changes = null,
        ?string $description = null
    ): AuditLog {
        $audit = AuditLog::logAction($action, $entityType, $entityId, $changes, $description);

        $this->info("Audit log created: {$action} on {$entityType}", [
            'audit_id' => $audit->id,
            'entity_id' => $entityId,
            'action' => $action,
            'entity_type' => $entityType,
        ]);

        return $audit;
    }

    private function log(string $level, string $message, array $data): void
    {
        $logData = array_merge($this->context, $data);

        Log::log($level, $message, $logData);
    }

    public function setContext(string $key, $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
