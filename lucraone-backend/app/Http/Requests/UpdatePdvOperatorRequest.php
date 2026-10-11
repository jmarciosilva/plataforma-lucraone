<?php

namespace App\Http\Requests;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\OperatorUsername;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edição de um operador de caixa.
 *
 * Nome e e-mail são da identidade GLOBAL e só são gravados quando o ator tem
 * autoridade sobre ela — o serviço recusa explicitamente, em vez de ignorar, para
 * a tela não parecer que salvou. Username e status são do perfil de caixa e
 * seguem a autoridade de operador.
 *
 * Senha não entra aqui: redefinir senha é ação própria, com confirmação e
 * autoridade própria, e exibir o campo junto dos demais convidaria a trocar a
 * senha de alguém sem querer.
 */
class UpdatePdvOperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(TenantContext $context): array
    {
        $tenantId = $context->id();

        // O parâmetro da rota é o id do PERFIL, não o da pessoa. A regra de
        // unicidade precisa ignorar a linha da PESSOA, senão o próprio e-mail
        // dela seria recusado em toda edição.
        $pessoaId = PdvOperatorProfile::consultaIsolada($tenantId)
            ->whereKey((string) $this->route('operator'))
            ->value('user_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')
                    ->whereNotNull('email')
                    ->ignore($pessoaId, 'id'),
            ],
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
            'status' => ['required', Rule::in([PdvOperatorProfile::STATUS_ACTIVE, PdvOperatorProfile::STATUS_INACTIVE])],
            'branch_ids' => ['nullable', 'array'],
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
            'branch_ids.*.exists' => 'filial inválida para este estabelecimento.',
            'email.unique' => 'este e-mail já está em uso.',
        ];
    }
}
