<?php

namespace App\Modules\Sales\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'product_id',
        'sku',
        'name',
        'quantity',
        'unit_price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function newFactory()
    {
        return OrderItemFactory::new();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
