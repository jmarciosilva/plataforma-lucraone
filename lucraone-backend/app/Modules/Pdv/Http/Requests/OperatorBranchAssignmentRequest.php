<?php

namespace App\Modules\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corpo da concessão de acesso de operador a uma filial.
 *
 * A filial vem da rota, e a pessoa do corpo. Só o identificador: nome, e-mail
 * e papéis não são aceitos aqui, porque este endpoint concede acesso e não
 * cadastra ninguém — misturar as duas coisas faria um fluxo de concessão virar
 * um fluxo de edição de identidade.
 *
 * O `status` também não é aceito: conceder cria ou reativa, revogar é o DELETE.
 * Deixar o cliente escrever o estado permitiria "revogar" por PUT sem passar
 * pela revogação em cascata das sessões.
 */
final class OperatorBranchAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'size:26'],
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
            '*.size' => 'Campo inválido.',
        ];
    }
}
