<?php

namespace App\Modules\Pdv\Http\Responses;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;

/**
 * Mapeia Terminal e seus vínculos para o contrato público do PDV.
 *
 * Campo por campo, de propósito. `return $terminal` ou `$model->toArray()`
 * faria com que qualquer coluna, cast ou relação acrescentada no futuro
 * entrasse no contrato sem ninguém decidir — é assim que `document`,
 * `legal_name` ou um atributo interno viram resposta pública por acidente.
 * Aqui, acrescentar um campo exige editar este arquivo.
 *
 * O conjunto é mínimo e só contém coluna que existe de verdade: `Company` não
 * tem `name`, tem `trade_name` (o `legal_name` e o `document` ficam de fora —
 * dado cadastral/fiscal entra junto dos contratos fiscais, não antes).
 * Configuração operacional — timezone, moeda, parâmetros de caixa — também não
 * foi incluída: não há consumidor ainda, e campo sem consumidor é contrato que
 * se paga para manter.
 */
final class PdvTerminalPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function para(Terminal $terminal, Tenant $tenant, Company $company, Branch $branch): array
    {
        return [
            'terminal' => [
                'id' => (string) $terminal->id,
                'name' => (string) $terminal->name,
                'status' => (string) $terminal->status,
                'installation_id' => (string) $terminal->installation_id,
            ],
            'tenant' => [
                'id' => (string) $tenant->id,
                'name' => (string) $tenant->name,
            ],
            'company' => [
                'id' => (string) $company->id,
                'trade_name' => (string) $company->trade_name,
            ],
            'branch' => [
                'id' => (string) $branch->id,
                'name' => (string) $branch->name,
                'code' => (string) $branch->code,
            ],
        ];
    }
}
