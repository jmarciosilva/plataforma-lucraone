<?php

namespace App\Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'string', 'uuid'],
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
