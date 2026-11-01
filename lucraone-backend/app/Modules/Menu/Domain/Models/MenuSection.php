<?php

namespace App\Modules\Menu\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\MenuSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuSection extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $table = 'menu_sections';

    protected $fillable = [
        'id',
        'branch_id',
        'tenant_id',
        'name',
        'position',
        'available',
    ];

    protected $casts = [
        'available' => 'boolean',
        'position' => 'integer',
    ];

    protected static function newFactory(): MenuSectionFactory
    {
        return MenuSectionFactory::new();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(MenuSectionItem::class)->orderBy('position');
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
