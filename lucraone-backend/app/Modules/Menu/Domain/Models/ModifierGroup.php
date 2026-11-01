<?php

namespace App\Modules\Menu\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Menu\Domain\ModifierGroupType;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\ModifierGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModifierGroup extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $table = 'modifier_groups';

    protected $fillable = [
        'id',
        'product_id',
        'branch_id',
        'tenant_id',
        'name',
        'type',
        'required',
        'min_selections',
        'max_selections',
        'position',
    ];

    protected $casts = [
        'required' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'position' => 'integer',
    ];

    protected static function newFactory(): ModifierGroupFactory
    {
        return ModifierGroupFactory::new();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function modifiers()
    {
        return $this->hasMany(Modifier::class, 'group_id')->orderBy('position');
    }

    public function isGlobal(): bool
    {
        return $this->branch_id === null;
    }

    public function isBranchOverride(): bool
    {
        return $this->branch_id !== null;
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('branch_id');
    }

    public function scopeForBranch($query, string $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function typeLabel(): string
    {
        return ModifierGroupType::LABELS[$this->type] ?? $this->type;
    }

    /**
     * Verifica se o grupo tem pelo menos um modifier disponível.
     * Relevante para grupos CHOICE obrigatórios.
     */
    public function hasAvailableModifiers(): bool
    {
        return $this->modifiers()->where('available', true)->exists();
    }
}
