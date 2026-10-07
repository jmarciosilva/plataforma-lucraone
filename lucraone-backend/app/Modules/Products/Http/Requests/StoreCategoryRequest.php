<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Core\Http\Rules\IdentificadorDisponivel;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(TenantContext $context): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-_]+$/',
                // Este request atende store e update do CategoryController, daí
                // o ignorarId: na criação a rota não tem parâmetro e vem null;
                // na edição vem o id, para a categoria não colidir consigo mesma.
                new IdentificadorDisponivel(
                    tabela: 'categories',
                    coluna: 'slug',
                    entidade: 'categoria',
                    campo: 'slug',
                    feminino: true,
                    tenantId: $context->id(),
                    ignorarId: $this->route('category'),
                ),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome da categoria é obrigatório',
            'slug.required' => 'Slug é obrigatório',
            'slug.slug' => 'Slug deve conter apenas letras, números, hífens e underscores',
        ];
    }
}
