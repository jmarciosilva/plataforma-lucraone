<?php

namespace App\Modules\Branches\Application;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function criarUnidade(string $tenantId, string $companyId, array $dados): Branch
    {
        return DB::transaction(function () use ($tenantId, $companyId, $dados) {
            $isHq = (bool) ($dados['is_headquarters'] ?? false);

            if ($isHq) {
                $this->garantirSemMatriz($tenantId, $companyId);
            }

            $branch = Branch::create([
                'id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'name' => $dados['name'],
                'code' => $dados['code'] ?? null,
                'document_override' => $dados['document_override'] ?? null,
                'email' => $dados['email'] ?? null,
                'phone' => $dados['phone'] ?? null,
                'timezone' => $dados['timezone'] ?? 'America/Sao_Paulo',
                'status' => 'ACTIVE',
                'is_headquarters' => $isHq,
                'max_pdv_terminals' => isset($dados['max_pdv_terminals']) ? (int) $dados['max_pdv_terminals'] : 5,
            ]);

            if (! empty($dados['address'])) {
                $this->salvarEndereco($branch, $dados['address'], true);
            }

            AuditLog::logAction(
                'branch.created',
                'branch',
                $branch->id,
                ['name' => $branch->name, 'is_headquarters' => $branch->is_headquarters],
                "Unidade \"{$branch->name}\" criada."
            );

            if ($isHq) {
                AuditLog::logAction(
                    'branch.headquarters_assigned',
                    'branch',
                    $branch->id,
                    ['branch_id' => $branch->id, 'company_id' => $companyId],
                    "Unidade \"{$branch->name}\" definida como matriz."
                );
            }

            return $branch;
        });
    }

    public function atualizarUnidade(Branch $branch, array $dados): Branch
    {
        return DB::transaction(function () use ($branch, $dados) {
            $isHqAntes = $branch->is_headquarters;
            $isHqDepois = (bool) ($dados['is_headquarters'] ?? false);

            if ($isHqDepois && ! $isHqAntes) {
                $this->garantirSemMatriz($branch->tenant_id, $branch->company_id, $branch->id);
            }

            $antes = [
                'name' => $branch->name,
                'status' => $branch->status,
                'is_headquarters' => $isHqAntes,
            ];

            $updateData = [
                'name' => $dados['name'],
                'code' => $dados['code'] ?? null,
                'document_override' => $dados['document_override'] ?? null,
                'email' => $dados['email'] ?? null,
                'phone' => $dados['phone'] ?? null,
                'timezone' => $dados['timezone'] ?? 'America/Sao_Paulo',
                'is_headquarters' => $isHqDepois,
            ];

            if (isset($dados['max_pdv_terminals'])) {
                $updateData['max_pdv_terminals'] = (int) $dados['max_pdv_terminals'];
            }

            $branch->update($updateData);

            if (! empty($dados['address'])) {
                $endereco = $branch->primaryAddress;
                if ($endereco) {
                    $endereco->update(array_merge($dados['address'], ['is_primary' => true]));
                } else {
                    $this->salvarEndereco($branch, $dados['address'], true);
                }
            }

            $depois = [
                'name' => $branch->name,
                'status' => $branch->status,
                'is_headquarters' => $isHqDepois,
            ];

            AuditLog::logAction(
                'branch.updated',
                'branch',
                $branch->id,
                ['antes' => $antes, 'depois' => $depois],
                "Unidade \"{$branch->name}\" atualizada."
            );

            if ($isHqDepois && ! $isHqAntes) {
                AuditLog::logAction(
                    'branch.headquarters_assigned',
                    'branch',
                    $branch->id,
                    ['branch_id' => $branch->id, 'company_id' => $branch->company_id],
                    "Unidade \"{$branch->name}\" definida como matriz."
                );
            }

            return $branch->fresh();
        });
    }

    public function toggleStatus(Branch $branch): Branch
    {
        $novoStatus = $branch->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $acao = $novoStatus === 'ACTIVE' ? 'branch.activated' : 'branch.deactivated';
        $descricao = $novoStatus === 'ACTIVE'
            ? "Unidade \"{$branch->name}\" reativada."
            : "Unidade \"{$branch->name}\" desativada.";

        $branch->update(['status' => $novoStatus]);

        AuditLog::logAction($acao, 'branch', $branch->id, ['status' => $novoStatus], $descricao);

        return $branch->fresh();
    }

    private function garantirSemMatriz(string $tenantId, string $companyId, ?string $excluirId = null): void
    {
        $query = Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('is_headquarters', true)
            ->lockForUpdate();

        if ($excluirId !== null) {
            $query->where('id', '!=', $excluirId);
        }

        $existente = $query->first();

        if ($existente !== null) {
            throw ValidationException::withMessages([
                'is_headquarters' => "Já existe uma matriz cadastrada (\"{$existente->name}\"). Desative-a antes de definir outra.",
            ]);
        }
    }

    private function salvarEndereco(Branch $branch, array $dados, bool $isPrimary): void
    {
        Address::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $branch->tenant_id,
            'addressable_type' => Branch::class,
            'addressable_id' => $branch->id,
            'street' => $dados['street'],
            'number' => $dados['number'],
            'complement' => $dados['complement'] ?? null,
            'district' => $dados['district'],
            'city' => $dados['city'],
            'state' => $dados['state'],
            'postal_code' => $dados['postal_code'],
            'country' => $dados['country'] ?? 'BR',
            'is_primary' => $isPrimary,
        ]);
    }
}
