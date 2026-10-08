<?php

namespace App\Modules\Terminals\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\TerminalAssignmentValidator;
use Database\Factories\TerminalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Terminal extends Model
{
    use HasFactory, HasTenant, HasUlid;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_BLOCKED = 'BLOCKED';

    public const STATUS_REVOKED = 'REVOKED';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_BLOCKED, self::STATUS_REVOKED];

    protected $fillable = ['tenant_id', 'company_id', 'branch_id', 'installation_id', 'name', 'status'];

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected $casts = ['id' => 'string', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(fn (Terminal $terminal) => app(TerminalAssignmentValidator::class)->validate($terminal));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function newFactory(): TerminalFactory
    {
        return TerminalFactory::new();
    }
}
