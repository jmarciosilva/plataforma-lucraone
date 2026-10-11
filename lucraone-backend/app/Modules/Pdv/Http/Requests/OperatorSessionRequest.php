<?php

namespace App\Modules\Pdv\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corpo do login do operador.
 *
 * Exatamente dois campos: `username` e `password`. Campo desconhecido recusa a
 * requisição inteira, em vez de ser ignorado — é por campo ignorado que um
 * cliente passa a mandar `terminal_id`, `branch_id` ou `tenant_id` e alguém, um
 * dia, começa a ler. Nesta API o contexto vem do Terminal autenticado, nunca do
 * corpo.
 *
 * `email` deixou de ser aceito aqui, e a troca é deliberada. A tela do caixa é
 * um teclado de membrana com fila na frente: ninguém digita
 * `jose.marcio@exemplo.com.br` vinte vezes por dia. Além disso, operador de
 * caixa pode não ter e-mail nenhum — e inventar um para ele seria pior que não
 * ter. O login humano por e-mail continua existindo, intacto, em
 * `POST /api/v1/auth/login` e no painel web; são portas diferentes, para
 * pessoas em situações diferentes.
 *
 * O formato do username é validado aqui apenas de forma frouxa (tamanho e
 * tipo). A regra real vive em `OperatorUsername` e é aplicada pelo serviço, que
 * responde a MESMA recusa genérica para formato impossível, username
 * inexistente e senha errada — validar a forma com rigor nesta camada
 * transformaria o 422 num oráculo de quais nomes existem.
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
            'username' => ['required', 'string', 'max:64'],
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
            '*.min' => 'Campo inválido.',
            '*.max' => 'Campo inválido.',
        ];
    }
}
