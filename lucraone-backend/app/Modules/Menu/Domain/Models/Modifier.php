<?php

namespace App\Modules\Menu\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Menu\Domain\ModifierGroupType;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\ModifierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modifier extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $table = 'modifiers';

    protected $fillable = [
        'id',
        'group_id',
        'tenant_id',
        'name',
        'price_delta',
        'position',
        'available',
    ];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'available' => 'boolean',
        'position' => 'integer',
    ];

    protected static function newFactory(): ModifierFactory
    {
        return ModifierFactory::new();
    }

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'group_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }

    /**
     * REMOVAL modifiers always have price_delta = 0 (business rule).
     */
    public function isRemoval(): bool
    {
        return $this->group->type === ModifierGroupType::REMOVAL;
    }
}
