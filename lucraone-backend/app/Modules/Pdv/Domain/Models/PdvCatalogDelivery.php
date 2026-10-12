<?php

namespace App\Modules\Pdv\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PdvCatalogDelivery extends Model
{
    public const PENDING = 'PENDING';

    public const AVAILABLE = 'AVAILABLE';

    public const TRANSFERRING = 'TRANSFERRING';

    public const RECEIVED = 'RECEIVED';

    public const APPLIED = 'APPLIED';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'pdv_catalog_deliveries';

    protected $guarded = [];

    protected $casts = ['received_at' => 'datetime', 'applied_at' => 'datetime'];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(PdvCatalogPublication::class, 'publication_id');
    }
}
