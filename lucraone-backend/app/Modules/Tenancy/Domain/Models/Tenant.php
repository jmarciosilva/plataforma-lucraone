<?php

namespace App\Modules\Tenancy\Domain\Models;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Events\TenantCreated;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $dispatchesEvents = [
        'created' => TenantCreated::class,
    ];

    protected $fillable = [
        'id',
        'name',
        'slug',
        'status',
        'plan',
        'timezone',
        'locale',
        'currency',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Um tenant possui muitas empresas.
     */
    public function companies()
    {
        return $this->hasMany(Company::class, 'tenant_id', 'id');
    }

    /**
     * Um tenant possui muitos usuários.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'tenant_id', 'id');
    }

    /**
     * Verifica se o tenant está ativo.
     */
    public function isActive(): bool
    {
        return $this->active && in_array($this->status, ['ACTIVE', 'TRIAL']);
    }

    /**
     * Retorna o nome formatado do tenant.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->name} ({$this->slug})"
        );
    }

    /**
     * Informar ao Laravel onde está a factory.
     */
    protected static function newFactory()
    {
        return TenantFactory::new();
    }
}
