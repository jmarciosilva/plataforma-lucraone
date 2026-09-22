<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Automation\Domain\Events\AutomationTriggered;
use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasTenant, HasUlid, SoftDeletes;

    /**
     * Anuncia o produto novo para as automações.
     *
     * O gancho é no model, não no controller, porque produto nasce por três
     * caminhos — API, painel e seeder — e o gatilho tem que valer nos três.
     */
    protected static function booted(): void
    {
        static::created(function (Product $product) {
            if (! $product->tenant_id) {
                return;
            }

            AutomationTriggered::dispatch($product->tenant_id, TriggerCatalog::PRODUTO_CRIADO, [
                'product_id' => $product->id,
                'nome' => $product->name,
                'sku' => $product->sku,
                'status' => $product->status,
                'company_id' => $product->company_id,
            ]);
        });
    }

    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    /**
     * Unidade base de venda e estoque: o que significa quantity = 1.
     */
    public const UNITS = [
        'UN' => 'Unidade',
        'KG' => 'Quilograma',
    ];

    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'sku',
        'barcode',
        'unit',
        'name',
        'description',
        'status',
    ];

    protected $attributes = [
        'unit' => 'UN',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Scope: Only active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: By SKU
     */
    public function scopeBySku($query, $sku)
    {
        return $query->where('sku', $sku);
    }

    /**
     * Relations
     */
    public function company()
    {
        return $this->belongsTo(\App\Modules\Companies\Domain\Models\Company::class);
    }

    public function categories()
    {
        return $this->belongsToMany(
            Category::class,
            'product_categories',
            'product_id',
            'category_id'
        )->withTimestamps();
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function packages()
    {
        return $this->hasMany(ProductPackage::class);
    }

    public function inventory()
    {
        return $this->hasOne(\App\Modules\Inventory\Domain\Models\Inventory::class);
    }
}
