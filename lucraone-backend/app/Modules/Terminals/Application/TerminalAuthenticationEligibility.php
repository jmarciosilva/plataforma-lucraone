<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\TerminalStructure;

/**
 * Este Terminal pode autenticar AGORA?
 *
 * Uma única definição, consultada em três momentos: no callback do Sanctum (a
 * cada requisição autenticada), no middleware de contexto de máquina e na
 * emissão de credencial. Sem isso a mesma regra existiria em três lugares e
 * divergiria no primeiro ajuste.
 *
 * Responde bool, e não exceção, porque o chamador principal é o callback do
 * Sanctum, cuja assinatura exige bool. Quem precisa de motivo — a emissão —
 * converte a recusa em exceção própria.
 *
 * A checagem é POR REQUISIÇÃO, deliberadamente. Um token válido e não expirado
 * precisa parar de valer no instante em que o Terminal é bloqueado ou a
 * estrutura acima dele sai de operação, sem depender de expiração, rotação ou
 * de alguém apagar a linha do token. É o mesmo princípio do `User::isActive()`
 * no ramo humano do callback.
 */
class TerminalAuthenticationEligibility
{
    public function allows(Terminal $terminal): bool
    {
        // ACTIVE é o único estado operacional. PENDING ainda não pareou,
        // BLOCKED está suspenso e REVOKED saiu de operação para sempre.
        if ($terminal->status !== Terminal::STATUS_ACTIVE) {
            return false;
        }

        // Invariante do domínio: ACTIVE sem instalação não deveria existir.
        // Conferir aqui é barato e evita que uma escrita fora do Eloquent —
        // SQL direto, bulk update, saveQuietly — abra uma porta.
        if ($terminal->installation_id === null) {
            return false;
        }

        // Sem lock: autenticação é leitura e roda a cada requisição. O pairing,
        // que decide uma transição, é quem usa lockForUpdate.
        //
        // Tenant passa pelo scope de soft delete de propósito: tenant removido
        // some da consulta e cai como não operacional. Company e Branch dispensam
        // o TenantScope porque o vínculo é reconferido em TerminalStructure.
        $tenant = Tenant::whereKey($terminal->tenant_id)->first();
        $company = Company::withoutGlobalScopes()->whereKey($terminal->company_id)->first();
        $branch = Branch::withoutGlobalScopes()->whereKey($terminal->branch_id)->first();

        if (! TerminalStructure::operacional($tenant, $company, $branch)) {
            return false;
        }

        // Diferente do pairing, aqui não há validador de escrita depois para
        // conferir os vínculos. Sem esta linha, um Terminal cujos IDs tenham
        // sido desalinhados fora do Eloquent autenticaria.
        return TerminalStructure::vinculosCoerentes($terminal, $company, $branch);
    }
}
