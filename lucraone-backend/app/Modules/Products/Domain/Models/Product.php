<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasTenant, SoftDeletes;

    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'sku',
        'name',
        'description',
        'status',
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
        );
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function inventory()
    {
        return $this->hasOne(\App\Modules\Inventory\Domain\Models\Inventory::class);
    }
}
