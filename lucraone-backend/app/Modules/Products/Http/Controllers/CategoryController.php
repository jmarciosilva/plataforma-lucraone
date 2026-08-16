<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Http\Requests\StoreCategoryRequest;
use App\Modules\Products\Http\Resources\CategoryResource;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * List all categories (hierarchical)
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->with(['children'])
            ->whereNull('parent_id')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Create a new category
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'tenant_id' => $this->tenantContext->id(),
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'parent_id' => $request->validated('parent_id'),
        ]);

        return response()->json(
            new CategoryResource($category),
            201
        );
    }

    /**
     * Show a specific category
     */
    public function show(string $id): CategoryResource
    {
        $category = Category::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($id);

        return new CategoryResource($category->load(['children']));
    }

    /**
     * Update a category
     */
    public function update(StoreCategoryRequest $request, string $id): CategoryResource
    {
        $category = Category::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($id);

        $category->update($request->validated());

        return new CategoryResource($category->fresh());
    }

    /**
     * Soft delete a category
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($id);

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * Get root categories only
     */
    public function roots(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->whereNull('parent_id')
            ->with(['children'])
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Get subcategories for a parent
     */
    public function children(string $parentId): AnonymousResourceCollection
    {
        $parent = Category::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($parentId);

        $children = $parent->children()->get();

        return CategoryResource::collection($children);
    }
}
