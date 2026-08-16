<?php

namespace App\Modules\Tenancy\Domain\Models;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Events\TenantCreated;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

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
     * Pessoas associadas a este estabelecimento.
     *
     * É many-to-many: a mesma pessoa pode estar associada a vários
     * estabelecimentos, com status independente em cada um.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot(['id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Apenas quem tem vínculo ativo aqui.
     */
    public function activeUsers()
    {
        return $this->users()->wherePivot('status', TenantUser::STATUS_ACTIVE);
    }

    /**
     * Vínculos como registros próprios.
     */
    public function memberships()
    {
        return $this->hasMany(TenantUser::class, 'tenant_id');
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
