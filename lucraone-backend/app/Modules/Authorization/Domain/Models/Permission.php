<?php

namespace App\Modules\Authorization\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenancy\Domain\Models\HasTenant;

class Permission extends Model
{
    use HasFactory, HasTenant;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'description',
    ];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission',
            'permission_id',
            'role_id'
        )->where('role_permission.tenant_id', $this->tenant_id)
            ->withTimestamps();
    }

    public static function forTenant($tenantId)
    {
        return self::where('tenant_id', $tenantId);
    }

    protected static function newFactory()
    {
        return \Database\Factories\PermissionFactory::new();
    }
}
