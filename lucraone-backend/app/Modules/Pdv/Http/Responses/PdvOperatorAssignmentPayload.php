<?php

namespace App\Modules\Pdv\Http\Responses;

use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;

/**
 * Mapeia a atribuição de operador para o contrato administrativo.
 *
 * Campo por campo, como os outros payloads do PDV. O que fica de fora é
 * deliberado: nada de credencial, sessão, hash ou dado cadastral da pessoa além
 * do necessário para identificá-la numa tela de administração.
 *
 * `granted_at` é o `created_at` da linha. Como conceder depois de revogar
 * reativa a MESMA linha, esse instante é o da primeira concessão; o histórico
 * completo de concessões e revogações está no AuditLog, que é onde o projeto
 * registra rastreabilidade.
 */
final class PdvOperatorAssignmentPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function comOperador(PdvOperatorBranchAssignment $atribuicao): array
    {
        return self::base($atribuicao) + [
            'operator' => $atribuicao->user === null ? null : [
                'id' => $atribuicao->user->id,
                'name' => (string) $atribuicao->user->name,
                'email' => (string) $atribuicao->user->email,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function comFilial(PdvOperatorBranchAssignment $atribuicao): array
    {
        return self::base($atribuicao) + [
            'branch' => $atribuicao->branch === null ? null : [
                'id' => $atribuicao->branch->id,
                'name' => (string) $atribuicao->branch->name,
                'code' => (string) $atribuicao->branch->code,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function base(PdvOperatorBranchAssignment $atribuicao): array
    {
        return [
            'id' => $atribuicao->id,
            'branch_id' => $atribuicao->branch_id,
            'operator_id' => $atribuicao->user_id,
            'status' => $atribuicao->status,
            'granted_by' => $atribuicao->granted_by,
            'granted_at' => $atribuicao->created_at?->toIso8601String(),
            'revoked_at' => $atribuicao->revoked_at?->toIso8601String(),
        ];
    }
}
