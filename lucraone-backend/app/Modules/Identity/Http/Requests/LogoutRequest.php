<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

class LogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization()
    {
        throw new AuthenticationException;
    }
}
