<?php

namespace App\Http\Requests;

use App\Modules\Menu\Domain\ModifierGroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', Rule::in(ModifierGroupType::ALL)],
            'required' => ['sometimes', 'boolean'],
            'min_selections' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'max_selections' => ['nullable', 'integer', 'min:1', 'max:255', 'gte:min_selections'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'branch_id' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->branch_id === '') {
            $this->merge(['branch_id' => null]);
        }

        if ($this->max_selections === '') {
            $this->merge(['max_selections' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $required = (bool) $this->boolean('required');
            $min = (int) ($this->min_selections ?? 0);

            if ($required && $min < 1) {
                $validator->errors()->add('min_selections', 'Um grupo obrigatório deve ter seleção mínima de pelo menos 1.');
            }
        });
    }
}
