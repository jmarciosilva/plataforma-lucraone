<?php

namespace App\Modules\Products\Domain\Models;

use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasTenant, SoftDeletes;

    protected static function newFactory()
    {
        return CategoryFactory::new();
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
        );
    }
}
