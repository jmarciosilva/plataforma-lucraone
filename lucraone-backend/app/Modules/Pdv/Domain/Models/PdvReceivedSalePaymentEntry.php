<?php

namespace App\Modules\Pdv\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvReceivedSalePaymentEntry extends Model
{
    protected $table = 'pdv_received_sale_payment_entries';

    public $timestamps = false;

    protected $fillable = ['sale_id', 'seq', 'method', 'amount'];

    protected $casts = [
        'seq' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PdvReceivedSale::class, 'sale_id');
    }
}
