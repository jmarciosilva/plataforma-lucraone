<?php

namespace App\Http\Requests;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\OperatorUsername;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro de um operador de caixa pelo painel.
 *
 * Dois modos, porque são duas operações com autoridades diferentes:
 *
 *   - `novo`      cria uma pessoa que ainda não existe no LucraOne;
 *   - `existente` habilita no caixa alguém que já existe.
 *
 * No modo `existente` NENHUM dado de identidade é aceito: nem nome, nem e-mail,
 * nem senha. É o que impede a tela de caixa de virar um caminho lateral para
 * reescrever a conta de uma pessoa compartilhada com outro estabelecimento.
 *
 * A autoridade real é verificada no serviço, não aqui: este FormRequest só
 * decide se o administrador pode abrir a tela.
 */
class StorePdvOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(TenantContext $context): array
    {
        $tenantId = $context->id();
        $novo = $this->input('modo') === 'novo';

        return [
            'modo' => ['required', Rule::in(['novo', 'existente'])],

            'username' => [
                'required',
                'string',
                'max:64',
                function (string $atributo, mixed $valor, \Closure $recusar) {
                    if (! OperatorUsername::valido((string) $valor)) {
                        $recusar('use de 3 a 32 caracteres, apenas letras, números, ponto, hífen ou sublinhado.');
                    }
                },
            ],

            // Pessoa nova.
            'name' => [Rule::requiredIf($novo), 'nullable', 'string', 'max:255'],
            // Opcional de propósito: caixa de lanchonete não tem e-mail, e
            // inventar um seria pior que não ter. Quando informado, continua
            // único no cadastro global.
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->whereNotNull('email'),
            ],
            'password' => [Rule::requiredIf($novo), 'nullable', 'string', 'min:8', 'confirmed'],

            // Pessoa existente. A validação de existência é global porque
            // identidade é global; quem amarra ao estabelecimento é o serviço,
            // que exige vínculo e aplica a contenção de delegação.
            'user_id' => [Rule::requiredIf(! $novo), 'nullable', 'string', 'size:26', Rule::exists(User::class, 'id')],

            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => [
                'string',
                'size:26',
                Rule::exists(Branch::class, 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_ids.required' => 'selecione ao menos uma filial.',
            'branch_ids.min' => 'selecione ao menos uma filial.',
            'branch_ids.*.exists' => 'filial inválida para este estabelecimento.',
            'email.unique' => 'este e-mail já está em uso.',
            'password.confirmed' => 'a confirmação não coincide com a senha.',
            'user_id.required' => 'escolha a pessoa que vai operar o caixa.',
            'user_id.exists' => 'pessoa não encontrada.',
        ];
    }
}
