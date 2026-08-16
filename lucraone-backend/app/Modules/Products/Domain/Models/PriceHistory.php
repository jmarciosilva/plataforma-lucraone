<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    use HasTenant, HasUlid;

    protected $table = 'price_histories';

    protected $fillable = [
        'tenant_id',
        'price_id',
        'product_id',
        'old_amount',
        'new_amount',
        'currency',
        'changed_by',
        'reason',
        'changed_at',
    ];

    protected $casts = [
        'old_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'changed_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function price()
    {
        return $this->belongsTo(Price::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(\App\Modules\Identity\Domain\Models\User::class, 'changed_by');
    }

    /**
     * Get percentage change
     */
    public function getPercentageChangeAttribute()
    {
        if ($this->old_amount == 0) {
            return null;
        }

        return (($this->new_amount - $this->old_amount) / $this->old_amount) * 100;
    }
}
