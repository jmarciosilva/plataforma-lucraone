<?php

namespace App\Modules\Inventory\Domain\Models;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\StockLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'company_id',
        'min_qty',
        'max_qty',
        'reorder_point',
    ];

    protected $casts = [
        'min_qty' => 'decimal:3',
        'max_qty' => 'decimal:3',
        'reorder_point' => 'decimal:3',
    ];

    protected static function newFactory()
    {
        return StockLevelFactory::new();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
