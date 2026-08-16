<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory, HasTenant, HasUlid;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_RESERVATION = 'reservation';

    public const TYPE_RELEASE = 'release';

    protected $fillable = [
        'tenant_id',
        'inventory_id',
        'product_id',
        'company_id',
        'user_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reason',
        'moved_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_before' => 'decimal:3',
        'quantity_after' => 'decimal:3',
        'moved_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return InventoryMovementFactory::new();
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
