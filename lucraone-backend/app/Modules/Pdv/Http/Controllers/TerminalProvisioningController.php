<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Http\Requests\TerminalProvisioningRequest;
use App\Modules\Pdv\Http\Responses\PdvErrorResponse;
use App\Modules\Pdv\Http\Responses\PdvTerminalPayload;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Application\TerminalProvisioningResult;
use App\Modules\Terminals\Application\TerminalProvisioningService;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/pdv/admin/terminals — provisionamento direto de Terminal.
 *
 * Caminho HUMANO, atrás de `auth:sanctum` + `HumanAdministrationContext`: PAT de
 * pessoa com `business:write` e estabelecimento resolvido pelo TenantResolver. É
 * o admin, na Retaguarda, criando o terminal a partir do `installation_id` que o
 * PDV gerou — não o PDV se apresentando. Por isso não há `terminal.context` aqui.
 *
 * Fino de propósito, como o PairTerminalController: adapta HTTP para o serviço e
 * o resultado para o contrato público. Unicidade, cota, coerência de vínculos e
 * emissão de credencial são `TerminalProvisioningService`, em uma única
 * transação. Reimplementar qualquer parte criaria uma segunda verdade.
 *
 * 201, e não 200: ao contrário do pairing — onde o Terminal já existia,
 * pré-cadastrado —, aqui esta chamada CRIA o recurso.
 *
 * O envelope é idêntico ao do pairing (terminal/tenant/company/branch +
 * credential) para que o cliente que migra do pareamento encontre a mesma forma;
 * o bloco `credential` segue o `CredentialLifecycleController@response`
 * (token_type Bearer, access_token, expires_at). Falhas de validação sobem como
 * ValidationException e viram o envelope de erro 422 do PDV.
 */
final class TerminalProvisioningController
{
    public function __construct(
        private TerminalProvisioningService $provisionamento,
        private TenantContext $tenantContexto,
    ) {}

    public function store(TerminalProvisioningRequest $request): JsonResponse
    {
        $ator = $request->user();
        if (! $ator instanceof User) {
            return PdvErrorResponse::make($request, PdvErrorResponse::CODE_FORBIDDEN, 'Operação administrativa não autorizada.', 403);
        }

        $resultado = $this->provisionamento->provision(
            $ator,
            $this->tenantContexto->id(),
            $request->string('installation_id')->value(),
            $request->string('name')->value(),
            $request->string('branch_id')->value(),
        );

        return response()
            ->json(['data' => $this->contrato($resultado)], 201)
            // Única resposta deste endpoint carrega um segredo de longa duração.
            // Sem isto, um proxy no caminho poderia guardar e reentregar a
            // credencial de um Terminal para outro.
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * @return array<string, mixed>
     */
    private function contrato(TerminalProvisioningResult $resultado): array
    {
        // Releitura pelos IDs que o resultado afirma — os rótulos públicos vêm
        // das entidades. Sem TenantScope porque os IDs vieram do próprio
        // provisionamento, que já validou a coerência dos vínculos.
        $terminal = Terminal::withoutGlobalScopes()->findOrFail($resultado->terminalId);
        $tenant = Tenant::findOrFail($resultado->tenantId);
        $company = Company::withoutGlobalScopes()->findOrFail($resultado->companyId);
        $branch = Branch::withoutGlobalScopes()->findOrFail($resultado->branchId);

        return PdvTerminalPayload::para($terminal, $tenant, $company, $branch) + [
            'credential' => [
                'token_type' => 'Bearer',
                // Única aparição do texto puro em toda a API. Não há como relê-lo:
                // o banco guarda apenas o SHA-256.
                'access_token' => $resultado->credential,
                'expires_at' => $resultado->credentialExpiresAt->toIso8601String(),
            ],
        ];
    }
}
