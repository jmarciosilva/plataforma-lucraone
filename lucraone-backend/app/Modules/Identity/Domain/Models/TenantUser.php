<?php

namespace App\Modules\Identity\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Vínculo de uma pessoa com um estabelecimento.
 *
 * O status daqui responde "esta pessoa pode operar NESTE estabelecimento?",
 * diferente de User::status, que responde "esta conta existe e está ativa?".
 */
class TenantUser extends Pivot
{
    use HasUlid;

    protected $table = 'tenant_user';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INVITED = 'INVITED';

    public const STATUS_INACTIVE = 'INACTIVE';

    public const STATUS_SUSPENDED = 'SUSPENDED';

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * O vínculo permite operar no estabelecimento?
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
