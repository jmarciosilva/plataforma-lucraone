<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Exceptions\MachineCredentialRefused;
use App\Modules\Terminals\Domain\InstallationId;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Provisionamento DIRETO de Terminal — o modelo de Linx/TOTVS/Stone.
 *
 * Diferente do pairing (ConsumeTerminalPairingCode), aqui não há código de uso
 * único nem interação em tempo real: o administrador informa, na Retaguarda, o
 * `installation_id` que o PDV gerou na instalação e cria o Terminal já ligado a
 * uma filial. O Terminal nasce ACTIVE e recebe a credencial de máquina na mesma
 * transação — o admin a insere uma única vez na configuração do PDV.
 *
 * A emissão da credencial é a MESMA do pairing (IssueTerminalMachineCredential),
 * reusada de propósito: a credencial resultante carrega as abilities de máquina
 * e funciona com o middleware `terminal.context` sem nenhum caminho novo. O que
 * muda é só COMO o Terminal chega a ACTIVE, não o que é uma credencial.
 */
class TerminalProvisioningService
{
    public function __construct(
        private IssueTerminalMachineCredential $emissao,
    ) {}

    /**
     * @param  User  $administrador  o sujeito humano que provisiona (auditoria)
     * @param  string  $tenantId  estabelecimento já resolvido pelo contexto humano
     * @param  string  $branchId  filial que receberá o Terminal
     *
     * @throws ValidationException quando a instalação já existe, a filial não
     *                             pertence ao estabelecimento ou a cota estourou
     */
    public function provision(
        User $administrador,
        string $tenantId,
        #[SensitiveParameter] string $installationId,
        string $name,
        string $branchId,
    ): TerminalProvisioningResult {
        // Normaliza fora da transação: estrutura inválida não precisa de lock.
        // O FormRequest já recusa o que não é UUID v4, mas o domínio é a
        // autoridade e canoniza para minúsculas antes de gravar e de comparar.
        try {
            $uuid = InstallationId::normalize($installationId);
        } catch (InvalidTerminalAssignment) {
            throw ValidationException::withMessages([
                'installation_id' => ['Identificador da instalação inválido.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($administrador, $tenantId, $uuid, $name, $branchId) {
                // `installation_id` é único no sistema inteiro: um PDV existe uma
                // só vez. Reler aqui dá o 422 limpo do caso comum; a corrida de
                // dois provisionamentos simultâneos fica por conta do índice
                // único, convertido abaixo.
                if (Terminal::withoutGlobalScopes()->where('installation_id', $uuid)->exists()) {
                    throw ValidationException::withMessages([
                        'installation_id' => ['Instalação já cadastrada.'],
                    ]);
                }

                // Filial relida sob lock e amarrada ao estabelecimento resolvido:
                // o id de outra loja simplesmente não casa e cai como "não
                // encontrada", a mesma recusa de um id inexistente.
                $branch = Branch::withoutGlobalScopes()
                    ->where('id', $branchId)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if ($branch === null) {
                    throw ValidationException::withMessages([
                        'branch_id' => ['Filial não encontrada neste estabelecimento.'],
                    ]);
                }

                $this->assegurarCota($branch);

                // ACTIVE já na criação, com installation_id: o observer de saving
                // (TerminalAssignmentValidator) confere a coerência dos vínculos e
                // exige installation_id para ACTIVE — ambos satisfeitos aqui.
                try {
                    $terminal = Terminal::create([
                        'id' => (string) Str::ulid(),
                        'tenant_id' => $tenantId,
                        'company_id' => $branch->company_id,
                        'branch_id' => $branch->id,
                        'installation_id' => $uuid,
                        'name' => $name,
                        'status' => Terminal::STATUS_ACTIVE,
                    ]);
                } catch (InvalidTerminalAssignment $e) {
                    throw ValidationException::withMessages(['name' => [$e->getMessage()]]);
                }

                // Emissão na MESMA transação: ou o Terminal nasce com credencial
                // viva, ou nada é persistido. Não deveria recusar — acabamos de
                // criá-lo ACTIVE e operacional —, mas se recusar, desfaz tudo em
                // vez de deixar um Terminal provisionado que não autentica.
                try {
                    $credencial = $this->emissao->issue($terminal);
                } catch (MachineCredentialRefused) {
                    throw ValidationException::withMessages([
                        'installation_id' => ['Não foi possível emitir a credencial do terminal.'],
                    ]);
                }

                AuditLog::logAction(
                    'terminal.provisioned',
                    'terminal',
                    $terminal->id,
                    ['name' => $terminal->name, 'branch_id' => $branch->id, 'provisioned_by' => $administrador->id],
                    "Terminal \"{$terminal->name}\" provisionado na filial \"{$branch->name}\"."
                );

                return new TerminalProvisioningResult(
                    $terminal->id,
                    $terminal->tenant_id,
                    $terminal->company_id,
                    $terminal->branch_id,
                    $terminal->installation_id,
                    $terminal->status,
                    $credencial->plainTextToken,
                    $credencial->expiresAt,
                );
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Corrida pelo mesmo installation_id: o índice único é a autoridade
            // final e devolve o mesmo 422 do caminho relido, nunca um 500.
            if (str_contains($exception->getMessage(), 'terminals_installation_id_unique')
                || str_contains($exception->getMessage(), 'terminals.installation_id')) {
                throw ValidationException::withMessages([
                    'installation_id' => ['Instalação já cadastrada.'],
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Mesma regra de cota do TerminalManagementService::verificarCota: revogados
     * não ocupam vaga; PENDING, ACTIVE e BLOCKED sim.
     */
    private function assegurarCota(Branch $branch): void
    {
        $ativos = Terminal::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [
                Terminal::STATUS_PENDING,
                Terminal::STATUS_ACTIVE,
                Terminal::STATUS_BLOCKED,
            ])
            ->count();

        if ($ativos >= $branch->max_pdv_terminals) {
            throw ValidationException::withMessages([
                'branch_id' => ["A filial já atingiu o limite de {$branch->max_pdv_terminals} terminal(is)."],
            ]);
        }
    }
}
