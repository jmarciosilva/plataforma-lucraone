<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Services\CalculoMargem;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    use HasTenant, HasUlid;

    public const EVENT_INITIAL = 'initial';

    public const EVENT_AMOUNT_CHANGED = 'amount_changed';

    public const EVENT_REFERENCE_COST_CHANGED = 'reference_cost_changed';

    public const EVENT_PRICE_REMOVED = 'price_removed';

    public const EVENT_LABELS = [
        self::EVENT_INITIAL => 'Preço inicial',
        self::EVENT_AMOUNT_CHANGED => 'Alteração de valor',
        self::EVENT_REFERENCE_COST_CHANGED => 'Alteração do custo de referência',
        self::EVENT_PRICE_REMOVED => 'Preço removido',
    ];

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
        'price_type',
        'event_type',
        'old_reference_cost_amount',
        'new_reference_cost_amount',
        'old_effective_margin_percentage',
        'new_effective_margin_percentage',
    ];

    protected $casts = [
        'old_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'changed_at' => 'datetime',
        'old_reference_cost_amount' => 'decimal:2',
        'new_reference_cost_amount' => 'decimal:2',
        'old_effective_margin_percentage' => 'decimal:4',
        'new_effective_margin_percentage' => 'decimal:4',
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
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get percentage change
     */
    public function getPercentageChangeAttribute()
    {
        return $this->new_amount === null ? null : CalculoMargem::percentual($this->old_amount, $this->new_amount);
    }
}
