<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasTenant, HasUlid, SoftDeletes;

    protected static function newFactory()
    {
        return CategoryFactory::new();
    }

    /**
     * Soft delete é um UPDATE, então a foreign key "on delete set null" não
     * dispara. Sem isto, os filhos ficariam apontando para um pai invisível.
     */
    protected static function booted(): void
    {
        static::deleting(function (Category $categoria) {
            $categoria->children()->update(['parent_id' => null]);
        });
    }

    protected $table = 'categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'parent_id',
    ];

    /**
     * Hierarchical category relationship
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get all descendants (nested children)
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get root categories
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Relations
     */
    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'product_categories',
            'category_id',
            'product_id'
        )->withTimestamps();
    }
}
