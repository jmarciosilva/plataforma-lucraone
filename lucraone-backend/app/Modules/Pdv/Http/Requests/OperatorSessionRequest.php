<?php

namespace App\Modules\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corpo do login do operador.
 *
 * Exatamente dois campos. Campo desconhecido recusa a requisição inteira, em
 * vez de ser ignorado: é por campo ignorado que um cliente passa a mandar
 * `terminal_id`, `branch_id` ou `tenant_id` e alguém, um dia, começa a ler.
 * Nesta API o contexto vem do Terminal autenticado, nunca do corpo.
 *
 * As mensagens não ecoam valor nenhum. `email` é validado como e-mail por ser
 * o formato do identificador, e isso não revela se a conta existe — a
 * existência é respondida pelo serviço, com a mesma recusa em todos os casos.
 */
final class OperatorSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (array_diff(array_keys($this->all()), array_keys($this->rules())) !== []) {
                $validator->errors()->add('request', 'Dados da requisição inválidos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Campo obrigatório.',
            '*.string' => 'Campo inválido.',
            '*.email' => 'Campo inválido.',
            '*.min' => 'Campo inválido.',
            '*.max' => 'Campo inválido.',
        ];
    }
}
