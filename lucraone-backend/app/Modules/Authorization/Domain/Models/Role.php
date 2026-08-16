<?php

namespace App\Modules\Authorization\Domain\Models;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
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

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission',
            'role_id',
            'permission_id'
        )
            ->withoutGlobalScopes()
            ->withTimestamps();
    }

    public function permissionsForTenant()
    {
        return $this->permissions()
            ->wherePivot('tenant_id', $this->tenant_id);
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_role',
            'role_id',
            'user_id'
        )->withTimestamps();
    }

    public function usersForTenant()
    {
        return $this->users()
            ->wherePivot('tenant_id', $this->tenant_id);
    }

    public function grantPermission(Permission $permission): void
    {
        if ($permission->tenant_id === $this->tenant_id && ! $this->hasPermission($permission)) {
            $this->permissions()->attach($permission->id, [
                'tenant_id' => $this->tenant_id,
            ]);
        }
    }

    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->permissionsForTenant()
            ->where('permissions.id', $permission->id)
            ->exists();
    }

    protected static function newFactory()
    {
        return RoleFactory::new();
    }
}
