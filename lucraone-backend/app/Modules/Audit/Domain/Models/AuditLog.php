<?php

namespace App\Modules\Audit\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use App\Modules\Identity\Domain\Models\User;

class AuditLog extends Model
{
    use HasFactory, HasTenant;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'changes',
        'ip_address',
        'user_agent',
        'request_id',
        'endpoint',
        'method',
        'status_code',
        'description',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function logAction(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $changes = null,
        ?string $description = null
    ): self {
        $user = auth()->user();
        $request = request();

        $tenantId = $user?->tenant_id;
        if (!$tenantId && app()->has(\App\Modules\Tenancy\Application\TenantContext::class)) {
            try {
                $tenantId = app(\App\Modules\Tenancy\Application\TenantContext::class)->id();
            } catch (\Exception $e) {
                // Se não conseguir resolver, deixa NULL
            }
        }

        return self::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-ID') ?? \Illuminate\Support\Str::ulid(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'status_code' => null,
            'description' => $description,
        ]);
    }

    public static function forTenant($tenantId)
    {
        return self::where('tenant_id', $tenantId);
    }

    public static function forUser($userId)
    {
        return self::where('user_id', $userId);
    }

    public static function forEntity($entityType, $entityId)
    {
        return self::where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    protected static function newFactory()
    {
        return \Database\Factories\AuditLogFactory::new();
    }
}
