<?php

namespace App\Modules\Pdv\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorização de uma pessoa para operar o caixa de UMA filial.
 *
 * O que ela acrescenta ao modelo existente é o recorte por filial. Vínculo com
 * o estabelecimento (`tenant_user`) e permissão (`access-pdv`) valem no
 * estabelecimento inteiro; esta linha é o que impede que valham em toda filial
 * dele. Papel administrativo não substitui a atribuição: um admin sem linha
 * ativa aqui não opera caixa nenhum, por decisão explícita.
 *
 * Usa HasTenant, como as outras entidades de negócio, para que o TenantScope
 * filtre as consultas pelo estabelecimento da requisição. Os caminhos que
 * rodam fora de um contexto resolvido — a revalidação por requisição de
 * máquina, por exemplo — consultam com `withoutGlobalScopes()` e filtram o
 * tenant à mão, a partir do Terminal autenticado.
 */
class PdvOperatorBranchAssignment extends Model
{
    use HasTenant, HasUlid;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_REVOKED = 'REVOKED';

    protected $table = 'pdv_operator_branch_assignments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'branch_id',
        'status',
        'granted_by',
        'revoked_at',
    ];

    protected $casts = [
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * A atribuição ativa desta tripla, sem depender do contexto da requisição.
     *
     * Usada pela revalidação das rotas de máquina, onde o sujeito autenticado é
     * o Terminal e o estabelecimento vem do vínculo dele — nunca de cabeçalho.
     */
    public static function ativaPara(string $tenantId, string $userId, string $branchId): ?self
    {
        return self::consultaIsolada($tenantId)
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    /**
     * A linha desta tripla, sob lock, para decidir concessão ou revogação.
     *
     * A tripla é única, então o lock é sobre uma linha só — e é ele que faz
     * concessão e revogação simultâneas convergirem para um estado, em vez de
     * uma sobrescrever a decisão da outra.
     */
    public static function travarPara(string $tenantId, string $userId, string $branchId): ?self
    {
        $consulta = self::consultaIsolada($tenantId)
            ->where('user_id', $userId)
            ->where('branch_id', $branchId);

        // Em instrução própria, e não encadeado: `lockForUpdate()` não existe no
        // builder do Eloquent — é encaminhado ao builder de consulta, que o
        // registra na própria consulta e devolve o builder do Eloquent de volta.
        // Encadear funciona em runtime, mas faz a análise estática perder a
        // associação com o model e tratar o resultado como linha crua. Chamado
        // aqui, o efeito é o mesmo e o `first()` seguinte continua tipado.
        $consulta->lockForUpdate();

        return $consulta->first();
    }

    /**
     * Consulta restrita a um estabelecimento informado explicitamente.
     *
     * `withoutGlobalScopes()` remove o TenantScope e o filtro volta aqui, no
     * `where` explícito: é o mesmo padrão do TenantAuthority e do
     * provisionamento, e vale para todo caminho que roda sem contexto.
     *
     * @return Builder<self>
     */
    public static function consultaIsolada(string $tenantId): Builder
    {
        return self::withoutGlobalScopes()->where('tenant_id', $tenantId);
    }
}
