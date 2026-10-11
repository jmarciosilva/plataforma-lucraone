<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OperatorBranchAccess;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Pdv\Http\Requests\OperatorBranchAssignmentRequest;
use App\Modules\Pdv\Http\Responses\PdvOperatorAssignmentPayload;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administração HUMANA das atribuições de operador a filiais.
 *
 * Autenticação humana existente (PAT com `business:write`) e estabelecimento
 * resolvido pelo TenantResolver, via `HumanAdministrationContext`. Nenhuma
 * credencial de máquina administra atribuição, e nenhuma atribuição é criada
 * por caminho de máquina.
 *
 * Filial e pessoa são carregadas EXPLICITAMENTE, com `withoutGlobalScopes()` e
 * filtro de estabelecimento à mão — não por route model binding. O binding roda
 * antes de este middleware resolver o contexto, e um scope que ainda não tem
 * tenant resolvido não filtra nada: o id de outra loja seria carregado e só
 * recusado depois, por uma checagem que alguém poderia remover. Mesmo padrão da
 * recuperação administrativa de credencial.
 *
 * Toda recusa é um 403 genérico. Não existe resposta que diferencie "não
 * existe" de "não é sua" — isso transformaria o endpoint em listador de ids.
 */
final class OperatorBranchAssignmentController
{
    public function __construct(
        private OperatorBranchAccess $acesso,
        private TenantContext $tenantContexto,
    ) {}

    /**
     * GET /admin/branches/{branch}/operators
     */
    public function index(Request $request, string $branch): JsonResponse
    {
        $filial = $this->filial($branch);
        $ator = $this->ator($request);

        // Leitura exige autoridade de leitura sobre filiais, não a de gestão:
        // quem só consulta a estrutura pode ver quem opera nela.
        if (! $ator->hasAnyPermission(OperatorPermission::CONSULTAR_ATRIBUICOES, $filial->tenant_id)) {
            throw OperatorSessionFailed::proibido();
        }

        return $this->sensivel(['data' => [
            'branch_id' => $filial->id,
            'operators' => $this->acesso->forBranch($filial)
                ->map(fn ($atribuicao) => PdvOperatorAssignmentPayload::comOperador($atribuicao))
                ->values()
                ->all(),
        ]]);
    }

    /**
     * POST /admin/branches/{branch}/operators
     *
     * Idempotente: conceder a quem já tem devolve 200 com a mesma atribuição,
     * em vez de criar uma segunda linha ou falhar.
     */
    public function store(OperatorBranchAssignmentRequest $request, string $branch): JsonResponse
    {
        $filial = $this->filial($branch);
        $operador = $this->operador((string) $request->validated('user_id'));

        $atribuicao = $this->acesso->grant($this->ator($request), $operador, $filial);

        return $this->sensivel(
            ['data' => PdvOperatorAssignmentPayload::comOperador($atribuicao->load('user'))],
            $atribuicao->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * DELETE /admin/branches/{branch}/operators/{user}
     *
     * Revoga e encerra, na mesma transação, as sessões operacionais daquela
     * pessoa naquela filial.
     */
    public function destroy(Request $request, string $branch, string $user): JsonResponse
    {
        $filial = $this->filial($branch);

        $this->acesso->revoke($this->ator($request), $this->operador($user), $filial);

        return $this->sensivel(null, 204);
    }

    /**
     * GET /admin/operators/{user}/branches
     */
    public function operatorBranches(Request $request, string $user): JsonResponse
    {
        $ator = $this->ator($request);
        $tenantId = $this->tenantContexto->id();
        $operador = $this->operador($user);

        if (! $ator->hasAnyPermission(OperatorPermission::CONSULTAR_ATRIBUICOES, $tenantId)) {
            throw OperatorSessionFailed::proibido();
        }

        return $this->sensivel(['data' => [
            'operator_id' => $operador->id,
            'branches' => $this->acesso->forOperator($tenantId, $operador)
                ->map(fn ($atribuicao) => PdvOperatorAssignmentPayload::comFilial($atribuicao))
                ->values()
                ->all(),
        ]]);
    }

    /**
     * @throws OperatorSessionFailed
     */
    private function filial(string $id): Branch
    {
        $filial = Branch::withoutGlobalScopes()
            ->whereKey($id)
            ->where('tenant_id', $this->tenantContexto->id())
            ->first();

        if ($filial === null) {
            throw OperatorSessionFailed::proibido();
        }

        return $filial;
    }

    /**
     * A pessoa alvo.
     *
     * Busca global porque identidade não pertence a um estabelecimento. Quem
     * amarra ao estabelecimento é a exigência de vínculo ativo, aplicada no
     * serviço — e uma pessoa sem vínculo recebe a mesma recusa genérica de uma
     * pessoa inexistente.
     *
     * @throws OperatorSessionFailed
     */
    private function operador(string $id): User
    {
        $operador = User::whereKey($id)->first();

        if ($operador === null) {
            throw OperatorSessionFailed::proibido();
        }

        return $operador;
    }

    private function ator(Request $request): User
    {
        $ator = $request->user();

        if (! $ator instanceof User) {
            throw OperatorSessionFailed::proibido();
        }

        return $ator;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function sensivel(?array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
