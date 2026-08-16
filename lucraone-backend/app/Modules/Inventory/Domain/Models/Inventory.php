<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'company_id',
        'quantity_on_hand',
        'reserved',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'reserved' => 'decimal:3',
    ];

    protected static function newFactory()
    {
        return InventoryFactory::new();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function stockLevel()
    {
        return $this->hasOne(StockLevel::class, 'product_id', 'product_id');
    }

    public function getAvailableAttribute(): string
    {
        return number_format((float) $this->quantity_on_hand - (float) $this->reserved, 3, '.', '');
    }
}
