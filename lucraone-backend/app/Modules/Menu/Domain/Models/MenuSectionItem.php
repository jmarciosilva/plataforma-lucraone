<?php

namespace App\Modules\Menu\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\MenuSectionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuSectionItem extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $table = 'menu_section_items';

    protected $fillable = [
        'id',
        'menu_section_id',
        'product_id',
        'tenant_id',
        'description',
        'price_override',
        'price_currency',
        'position',
        'available',
    ];

    protected $casts = [
        'price_override' => 'decimal:2',
        'available' => 'boolean',
        'position' => 'integer',
    ];

    protected static function newFactory(): MenuSectionItemFactory
    {
        return MenuSectionItemFactory::new();
    }

    public function section()
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Preço efetivo a exibir no cardápio (R2-B).
     *
     * Precedência: price_override > Price[type=sale] > null.
     * Retorna null quando nenhum preço está configurado — a UI deve alertar.
     *
     * @return array{amount: string, currency: string, source: string}|null
     */
    public function effectivePrice(): ?array
    {
        if ($this->price_override !== null) {
            return [
                'amount' => $this->price_override,
                'currency' => $this->price_currency ?? 'BRL',
                'source' => 'menu_override',
            ];
        }

        $price = $this->product
            ->prices()
            ->where('type', Price::TYPE_SALE)
            ->orderByDesc('created_at')
            ->first();

        if ($price) {
            return [
                'amount' => $price->amount,
                'currency' => $price->currency,
                'source' => 'product_sale_price',
            ];
        }

        return null;
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }
}
