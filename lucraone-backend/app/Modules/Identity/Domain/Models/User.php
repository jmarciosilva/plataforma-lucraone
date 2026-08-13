<?php

namespace App\Modules\Identity\Domain\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Modules\Tenancy\Domain\Models\HasTenant;

class User extends Model implements AuthenticatableContract
{
    use HasFactory, HasTenant, HasApiTokens, Authenticatable, Notifiable;

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
        return \Database\Factories\UserFactory::new();
    }
}
