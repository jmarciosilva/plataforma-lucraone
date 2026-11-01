<?php

namespace App\Http\Requests;

use App\Modules\Menu\Domain\ModifierGroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'string', Rule::in(ModifierGroupType::ALL)],
            'required' => ['sometimes', 'boolean'],
            'min_selections' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'max_selections' => ['nullable', 'integer', 'min:1', 'max:255', 'gte:min_selections'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->max_selections === '') {
            $this->merge(['max_selections' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $required = $this->has('required') ? $this->boolean('required') : null;
            $min = $this->has('min_selections') ? (int) $this->min_selections : null;

            if ($required === true && $min !== null && $min < 1) {
                $validator->errors()->add('min_selections', 'Um grupo obrigatório deve ter seleção mínima de pelo menos 1.');
            }
        });
    }
}
