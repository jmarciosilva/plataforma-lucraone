<?php

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Authorization\Application\Traits\HasRole;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * Identidade global — uma pessoa, uma conta, uma senha.
 *
 * Não usa HasTenant de propósito: o usuário não pertence a um estabelecimento,
 * ele se associa a vários através de tenant_user. Quem determina o
 * estabelecimento em uso na requisição é o TenantContext.
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, HasRole, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
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

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    /**
     * Estabelecimentos aos quais esta pessoa está associada.
     */
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot(['id', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Apenas os estabelecimentos onde o vínculo está ativo.
     */
    public function activeTenants()
    {
        return $this->tenants()->wherePivot('status', TenantUser::STATUS_ACTIVE);
    }

    /**
     * Vínculos como registros próprios (para ler ou alterar o status).
     */
    public function memberships()
    {
        return $this->hasMany(TenantUser::class);
    }

    /**
     * Vínculo com um estabelecimento específico, se existir.
     */
    public function membershipFor(string $tenantId): ?TenantUser
    {
        return $this->memberships()->where('tenant_id', $tenantId)->first();
    }

    /**
     * A pessoa pode operar neste estabelecimento agora?
     *
     * Exige conta ativa E vínculo ativo — as duas coisas.
     */
    public function canAccessTenant(string $tenantId): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->memberships()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantUser::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Associa a pessoa a um estabelecimento (ou atualiza o vínculo existente).
     */
    public function joinTenant(string $tenantId, string $status = TenantUser::STATUS_ACTIVE): TenantUser
    {
        $vinculo = TenantUser::firstOrNew([
            'tenant_id' => $tenantId,
            'user_id' => $this->id,
        ]);

        // O ULID é atribuído aqui de propósito: em models Pivot o hook de
        // criação do HasUlid não dispara, e o MySQL rejeita a linha sem id.
        if (! $vinculo->exists) {
            $vinculo->id = (string) \Illuminate\Support\Str::ulid();
            $vinculo->joined_at = now();
        }

        $vinculo->status = $status;
        $vinculo->save();

        return $vinculo;
    }

    /**
     * Estabelecimentos ativos, para o seletor de login e a troca pelo menu.
     *
     * @return Collection<int, Tenant>
     */
    public function estabelecimentosDisponiveis(): Collection
    {
        return $this->activeTenants()->orderBy('name')->get();
    }

    /**
     * A conta está ativa? (independente de estabelecimento)
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
