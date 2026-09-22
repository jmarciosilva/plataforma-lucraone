<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\ProductPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Embalagem comercial (caixa, fardo, multipack) de um Product base.
 *
 * Não é variante: estoque, preço e pedido continuam no Product. A embalagem só
 * diz quantas unidades base contém (`factor`) e qual código de barras a
 * identifica.
 */
class ProductPackage extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected static function newFactory()
    {
        return ProductPackageFactory::new();
    }

    protected $table = 'product_packages';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'barcode',
        'factor',
    ];

    protected $casts = [
        'factor' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
