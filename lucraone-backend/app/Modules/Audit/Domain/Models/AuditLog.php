<?php

namespace App\Modules\Audit\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

        // O estabelecimento vem do contexto da requisição. Desde o F1.8 o
        // usuário pode estar associado a vários, então não há um tenant_id
        // nele para consultar.
        $tenantId = null;

        if (app()->has(TenantContext::class)) {
            try {
                $tenantId = app(TenantContext::class)->id();
            } catch (\Exception $e) {
                // Contexto não resolvido — cai no vínculo único abaixo
            }
        }

        // Fora de uma requisição (jobs, comandos): se a pessoa tem um único
        // vínculo ativo, não há ambiguidade sobre onde registrar.
        if (! $tenantId && $user) {
            $vinculos = $user->memberships()->where('status', 'ACTIVE')->pluck('tenant_id');

            if ($vinculos->count() === 1) {
                $tenantId = $vinculos->first();
            }
        }

        return self::create([
            'id' => Str::ulid(),
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-ID') ?? Str::ulid(),
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
        return AuditLogFactory::new();
    }
}
