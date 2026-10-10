<?php

namespace App\Modules\Terminals\Domain;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;

/**
 * A estrutura acima do Terminal está operacional, e é a dele?
 *
 * Única definição de "operacional" para máquina, consultada tanto pelo pairing
 * (PDV-BE-03) quanto pela autenticação de máquina (PDV-BE-04). O que varia
 * entre os dois é apenas COMO os três pais são carregados: o pairing os lê com
 * lockForUpdate dentro da transação de consumo, a autenticação os lê sem lock a
 * cada requisição. Por isso os modelos entram por parâmetro — centralizar a
 * carga aqui obrigaria uma das duas a usar a estratégia errada.
 *
 * O que NÃO está aqui é o estado do próprio Terminal: o pairing exige PENDING e
 * a autenticação exige ACTIVE. São regras opostas e cada chamador aplica a sua.
 */
final class TerminalStructure
{
    /**
     * Os três pais existem e estão em estado operacional?
     *
     * Ausente conta como não operacional: um pai apagado ou, no caso do Tenant,
     * soft-deleted, não pode destravar máquina nenhuma.
     *
     * Só estado. A coerência dos vínculos é pergunta separada — ver
     * `vinculosCoerentes()` — porque no pairing ela já é respondida pelo
     * TerminalAssignmentValidator, com motivo de recusa próprio. Juntar as duas
     * aqui trocaria o motivo que o pairing devolve hoje.
     */
    public static function operacional(?Tenant $tenant, ?Company $company, ?Branch $branch): bool
    {
        if ($tenant === null || $company === null || $branch === null) {
            return false;
        }

        return $tenant->isActive() && $company->isActive() && $branch->isActive();
    }

    /**
     * Os três IDs do Terminal descrevem a mesma hierarquia?
     *
     * Reconferido porque as FKs do banco são individuais: nenhuma FK composta
     * garante que a Branch pertença à Company informada, nem que as duas
     * pertençam ao Tenant informado. Quem lê o Terminal para autenticar não
     * passa pelo validador de escrita, então precisa fechar isso aqui.
     */
    public static function vinculosCoerentes(Terminal $terminal, Company $company, Branch $branch): bool
    {
        return $company->tenant_id === $terminal->tenant_id
            && $branch->tenant_id === $terminal->tenant_id
            && $branch->company_id === $terminal->company_id;
    }
}
