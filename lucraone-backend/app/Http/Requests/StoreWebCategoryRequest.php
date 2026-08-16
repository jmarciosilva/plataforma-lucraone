<?php

namespace App\Http\Requests;

use App\Modules\Products\Domain\Models\Category;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreWebCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ? Gate::forUser($this->user())->allows('create', Category::class)
            : false;
    }

    public function rules(TenantContext $context): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('categories', 'slug')
                    ->where('tenant_id', $context->id())
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists(Category::class, 'id')->where('tenant_id', $context->id()),
                function (string $attribute, mixed $value, \Closure $fail) use ($category) {
                    if ($category && $value === $category->id) {
                        $fail('a categoria não pode ser pai dela mesma.');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }
}
