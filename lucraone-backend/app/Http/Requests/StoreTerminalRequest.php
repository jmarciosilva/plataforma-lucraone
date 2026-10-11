<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'A filial é obrigatória.',
            'branch_id.size' => 'Filial inválida.',
            'name.required' => 'O nome do terminal é obrigatório.',
            'name.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
        ];
    }
}
