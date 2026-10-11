<?php

namespace App\Modules\Terminals\Application;

use App\Modules\Audit\Domain\Models\AuditLog;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Terminals\Domain\Exceptions\InvalidTerminalAssignment;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TerminalManagementService
{
    public function criar(string $tenantId, array $dados): Terminal
    {
        return DB::transaction(function () use ($tenantId, $dados) {
            $branch = Branch::withoutGlobalScopes()
                ->where('id', $dados['branch_id'])
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if ($branch === null) {
                throw ValidationException::withMessages(['branch_id' => ['Filial não encontrada neste estabelecimento.']]);
            }

            $this->verificarCota($branch);

            try {
                $terminal = Terminal::create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'name' => $dados['name'],
                    'status' => Terminal::STATUS_PENDING,
                ]);
            } catch (InvalidTerminalAssignment $e) {
                throw ValidationException::withMessages(['name' => [$e->getMessage()]]);
            }

            AuditLog::logAction(
                'terminal.created',
                'terminal',
                $terminal->id,
                ['name' => $terminal->name, 'branch_id' => $branch->id],
                "Terminal \"{$terminal->name}\" cadastrado na filial \"{$branch->name}\"."
            );

            return $terminal;
        });
    }

    public function atualizar(Terminal $terminal, array $dados): Terminal
    {
        return DB::transaction(function () use ($terminal, $dados) {
            $antes = ['name' => $terminal->name];

            try {
                $terminal->update(['name' => $dados['name']]);
            } catch (InvalidTerminalAssignment $e) {
                throw ValidationException::withMessages(['name' => [$e->getMessage()]]);
            }

            AuditLog::logAction(
                'terminal.updated',
                'terminal',
                $terminal->id,
                ['antes' => $antes, 'depois' => ['name' => $terminal->name]],
                "Terminal \"{$terminal->name}\" atualizado."
            );

            return $terminal->fresh();
        });
    }

    public function bloquear(Terminal $terminal): Terminal
    {
        if ($terminal->status !== Terminal::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['status' => ['Somente terminais ATIVOS podem ser bloqueados.']]);
        }

        $terminal->update(['status' => Terminal::STATUS_BLOCKED]);

        AuditLog::logAction(
            'terminal.blocked',
            'terminal',
            $terminal->id,
            ['terminal_id' => $terminal->id],
            "Terminal \"{$terminal->name}\" bloqueado."
        );

        return $terminal->fresh();
    }

    public function desbloquear(Terminal $terminal): Terminal
    {
        if ($terminal->status !== Terminal::STATUS_BLOCKED) {
            throw ValidationException::withMessages(['status' => ['Somente terminais BLOQUEADOS podem ser desbloqueados.']]);
        }

        $terminal->update(['status' => Terminal::STATUS_ACTIVE]);

        AuditLog::logAction(
            'terminal.unblocked',
            'terminal',
            $terminal->id,
            ['terminal_id' => $terminal->id],
            "Terminal \"{$terminal->name}\" desbloqueado."
        );

        return $terminal->fresh();
    }

    public function revogar(Terminal $terminal): Terminal
    {
        if ($terminal->status === Terminal::STATUS_REVOKED) {
            throw ValidationException::withMessages(['status' => ['Terminal já está revogado.']]);
        }

        $terminal->update(['status' => Terminal::STATUS_REVOKED]);

        AuditLog::logAction(
            'terminal.revoked',
            'terminal',
            $terminal->id,
            ['terminal_id' => $terminal->id],
            "Terminal \"{$terminal->name}\" revogado permanentemente."
        );

        return $terminal->fresh();
    }

    public function registrarHeartbeat(Terminal $terminal): void
    {
        // updateQuietly bypasses the saving observer (TerminalAssignmentValidator)
        // which runs a full structural check — unnecessary for a timestamp update.
        $terminal->updateQuietly(['last_seen_at' => now()]);
    }

    // -------------------------------------------------------------------------

    private function verificarCota(Branch $branch): void
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
