<?php

namespace App\Modules\Pdv\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvReceivedSaleItem extends Model
{
    protected $table = 'pdv_received_sale_items';

    public $timestamps = false;

    protected $fillable = [
        'sale_id', 'line_number', 'product_id', 'product_name', 'sku',
        'unit', 'quantity', 'unit_price', 'discount_amount', 'total_amount',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PdvReceivedSale::class, 'sale_id');
    }
}
