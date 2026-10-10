<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Pdv\Http\Requests\PairTerminalRequest;
use App\Modules\Pdv\Http\Responses\PdvTerminalPayload;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Application\ConsumeTerminalPairingCode;
use App\Modules\Terminals\Application\TerminalProvisioningResult;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * POST /api/v1/pdv/terminals/pair — consome o pareamento e entrega a credencial.
 *
 * Fino de propósito: adapta HTTP para o serviço e o resultado para o contrato
 * público. Nada de hash, selector, prazo, attempts, lock, transição de status
 * ou emissão de credencial aqui — tudo isso é `ConsumeTerminalPairingCode`, que
 * decide em uma única transação desde o PDV-BE-04. Reimplementar qualquer parte
 * criaria uma segunda verdade sobre pareamento.
 *
 * 200, e não 201: o Terminal já existia, pré-cadastrado pela administração. O
 * que esta chamada cria é o vínculo da instalação e a credencial, não o recurso.
 *
 * A falha não é tratada aqui: `PairingFailed` sobe e é convertida no envelope
 * genérico pelo renderizador do PDV, justamente para que o motivo interno não
 * tenha caminho até a resposta.
 */
class PairTerminalController extends Controller
{
    public function __construct(
        private ConsumeTerminalPairingCode $consumo
    ) {}

    public function __invoke(PairTerminalRequest $request): JsonResponse
    {
        $resultado = $this->consumo->consume(
            $request->string('pairing_code')->value(),
            $request->string('installation_id')->value(),
        );

        return response()
            ->json(['data' => $this->contrato($resultado)])
            // Única resposta da API que carrega um segredo de longa duração.
            // Sem isto, um proxy no caminho da loja poderia guardar e reentregar
            // a credencial de um Terminal para outro.
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * @return array<string, mixed>
     */
    private function contrato(TerminalProvisioningResult $resultado): array
    {
        // Releitura pelos IDs que o resultado afirma: o serviço devolve IDs, e
        // os rótulos públicos (nome do caixa, da filial, nome fantasia) vêm das
        // entidades. Sem TenantScope porque ainda não há contexto resolvido —
        // esta rota é pública — e os três IDs vieram do próprio provisionamento,
        // que já validou a coerência dos vínculos.
        $terminal = Terminal::withoutGlobalScopes()->findOrFail($resultado->terminalId);
        $tenant = Tenant::findOrFail($resultado->tenantId);
        $company = Company::withoutGlobalScopes()->findOrFail($resultado->companyId);
        $branch = Branch::withoutGlobalScopes()->findOrFail($resultado->branchId);

        return PdvTerminalPayload::para($terminal, $tenant, $company, $branch) + [
            'credential' => [
                'token_type' => 'Bearer',
                // Única aparição do texto puro em toda a API. Não há como
                // relê-lo: o banco guarda apenas o SHA-256.
                'access_token' => $resultado->credential,
                'expires_at' => $resultado->credentialExpiresAt->toIso8601String(),
            ],
        ];
    }
}
