<?php

namespace App\Modules\Authorization\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use App\Modules\Identity\Domain\Models\User;

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
        )->where('role_permission.tenant_id', $this->tenant_id)
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_role',
            'role_id',
            'user_id'
        )->where('user_role.tenant_id', $this->tenant_id)
            ->withTimestamps();
    }

    public function grantPermission(Permission $permission): void
    {
        if (!$this->hasPermission($permission)) {
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
        return $this->permissions()
            ->where('permission_id', $permission->id)
            ->exists();
    }

    protected static function newFactory()
    {
        return \Database\Factories\RoleFactory::new();
    }
}
