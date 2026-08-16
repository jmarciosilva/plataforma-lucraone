<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\PriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected static function newFactory()
    {
        return PriceFactory::new();
    }

    protected $table = 'prices';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'currency',
        'amount',
        'type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Price types: sale, cost, suggested_retail
     */
    const TYPE_COST = 'cost';
    const TYPE_SALE = 'sale';
    const TYPE_SUGGESTED_RETAIL = 'suggested_retail';

    /**
     * Relations
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function histories()
    {
        return $this->hasMany(PriceHistory::class);
    }

    /**
     * Scope: Only sale prices
     */
    public function scopeSale($query)
    {
        return $query->where('type', self::TYPE_SALE);
    }

    /**
     * Scope: Only cost prices
     */
    public function scopeCost($query)
    {
        return $query->where('type', self::TYPE_COST);
    }

    /**
     * Get margin percentage
     */
    public function getMarginPercentageAttribute()
    {
        $costPrice = $this->product->prices()->cost()->first();
        if (!$costPrice || $costPrice->amount == 0) {
            return null;
        }

        return (($this->amount - $costPrice->amount) / $costPrice->amount) * 100;
    }
}
