<?php

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Authorization\Application\Traits\HasRole;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, HasRole, HasTenant, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'email',
        'password',
        'status',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant ao qual o usuário pertence.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Modules\Tenancy\Domain\Models\Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isInvited(): bool
    {
        return $this->status === 'INVITED';
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
