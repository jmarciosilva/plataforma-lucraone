<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'document_override' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'is_headquarters' => ['nullable', 'boolean'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.number' => ['nullable', 'string', 'max:50'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.district' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.state' => ['nullable', 'string', 'size:2'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'size:2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_headquarters' => $this->boolean('is_headquarters'),
        ]);
    }
}
