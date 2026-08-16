<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Products\Http\Requests\StoreProductRequest;
use App\Modules\Products\Http\Requests\UpdateProductRequest;
use App\Modules\Products\Http\Resources\ProductResource;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * List products with pagination and filters
     */
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->with(['categories', 'prices'])
            ->paginate(20);

        return ProductResource::collection($products);
    }

    /**
     * Create a new product
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create([
            'tenant_id' => $this->tenantContext->id(),
            'company_id' => $request->validated('company_id'),
            'sku' => $request->validated('sku'),
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
        ]);

        // Attach categories if provided
        if ($request->has('category_ids')) {
            $product->categories()->attach(
                $request->validated('category_ids')
            );
        }

        return response()->json(
            new ProductResource($product->load(['categories'])),
            201
        );
    }

    /**
     * Show a specific product
     */
    public function show(string $id): ProductResource
    {
        $product = Product::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($id);

        return new ProductResource($product->load(['categories', 'prices']));
    }

    /**
     * Update a product
     */
    public function update(UpdateProductRequest $request, string $id): ProductResource
    {
        $product = Product::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($id);

        $product->update($request->validated());

        // Update categories if provided
        if ($request->has('category_ids')) {
            $product->categories()->sync(
                $request->validated('category_ids')
            );
        }

        return new ProductResource($product->fresh(['categories', 'prices']));
    }

    /**
     * Soft delete a product
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->findOrFail($id);

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Search products by SKU or name
     */
    public function search(string $query): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where(function ($q) use ($query) {
                $q->where('sku', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%");
            })
            ->with(['categories', 'prices'])
            ->paginate(20);

        return ProductResource::collection($products);
    }

    /**
     * Get products by status
     */
    public function byStatus(string $status): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', $status)
            ->with(['categories', 'prices'])
            ->paginate(20);

        return ProductResource::collection($products);
    }
}
