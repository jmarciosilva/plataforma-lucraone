<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebCategoryRequest;
use App\Http\Requests\UpdateWebCategoryRequest;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryWebController extends Controller
{
    public function index(Request $request, TenantContext $context)
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with(['parent'])
            ->withCount(['children', 'products'])
            ->when($request->input('trashed') === 'with', fn ($query) => $query->withTrashed())
            ->when($request->input('trashed') === 'only', fn ($query) => $query->onlyTrashed())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('parent_id')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('categories.index', [
            'categories' => $categories,
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => $this->breadcrumbs(),
            'trashedOptions' => [
                '' => 'ativas',
                'with' => 'ativas e arquivadas',
                'only' => 'somente arquivadas',
            ],
        ]);
    }

    public function create(TenantContext $context)
    {
        Gate::authorize('create', Category::class);

        return view('categories.create', [
            'category' => new Category,
            'parents' => $this->parents(),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => 'nova']],
        ]);
    }

    public function store(StoreWebCategoryRequest $request, TenantContext $context)
    {
        $category = Category::create([
            ...$request->validated(),
            'tenant_id' => $context->id(),
        ]);

        return redirect()
            ->route('catalog.categories.index')
            ->with('sucesso', 'categoria criada.');
    }

    public function show(string $category, TenantContext $context)
    {
        $category = Category::withTrashed()
            ->where('tenant_id', $context->id())
            ->findOrFail($category);

        Gate::authorize('view', $category);

        return view('categories.show', [
            'category' => $category->load(['parent', 'children', 'products']),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $category->name]],
        ]);
    }

    public function edit(Category $category, TenantContext $context)
    {
        Gate::authorize('update', $category);

        return view('categories.edit', [
            'category' => $category,
            'parents' => $this->parents($category->id),
            'tenantNome' => $context->tenant()->name,
            'breadcrumbs' => [...$this->breadcrumbs(), ['label' => $category->name, 'url' => route('catalog.categories.show', $category)], ['label' => 'editar']],
        ]);
    }

    public function update(UpdateWebCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return redirect()
            ->route('catalog.categories.show', $category)
            ->with('sucesso', 'categoria atualizada.');
    }

    public function destroy(Category $category)
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return redirect()
            ->route('catalog.categories.index', ['trashed' => 'with'])
            ->with('sucesso', 'categoria arquivada.');
    }

    public function restore(string $category, TenantContext $context)
    {
        $category = Category::onlyTrashed()
            ->where('tenant_id', $context->id())
            ->findOrFail($category);

        Gate::authorize('restore', $category);

        $category->restore();

        return redirect()
            ->route('catalog.categories.show', $category)
            ->with('sucesso', 'categoria restaurada.');
    }

    private function breadcrumbs(): array
    {
        return [
            ['label' => 'dashboard', 'url' => route('dashboard')],
            ['label' => 'produtos', 'url' => route('catalog.products.index')],
            ['label' => 'categorias', 'url' => route('catalog.categories.index')],
        ];
    }

    private function parents(?string $exceptId = null)
    {
        return Category::query()
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('name')
            ->get();
    }
}
