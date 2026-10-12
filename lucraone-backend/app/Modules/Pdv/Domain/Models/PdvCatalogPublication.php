<?php

namespace App\Modules\Pdv\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PdvCatalogPublication extends Model
{
    public const GENERATING = 'GENERATING';

    public const PUBLISHED = 'PUBLISHED';

    public const FAILED = 'FAILED';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'pdv_catalog_publications';

    protected $guarded = [];

    public function deliveries(): HasMany
    {
        return $this->hasMany(PdvCatalogDelivery::class, 'publication_id');
    }
}
