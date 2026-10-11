<?php

namespace App\Modules\Pdv\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\OperatorUsername;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perfil de caixa de uma pessoa num estabelecimento.
 *
 * Responde "esta pessoa tem identificador de caixa aqui, e ele está ativo?". A
 * pergunta "em qual filial ela opera" continua sendo da
 * PdvOperatorBranchAssignment, que não foi duplicada; e "quem ela é" continua
 * sendo do `users`.
 *
 * O `status` daqui é o terceiro de uma escada deliberada:
 *
 *   - `users.status`       → a conta existe e está ativa (identidade global);
 *   - `tenant_user.status` → a pessoa pode operar NESTE estabelecimento;
 *   - este `status`        → a pessoa pode operar o CAIXA deste estabelecimento.
 *
 * Desativar um operador mexe só no último. É o que permite tirar do caixa quem
 * continua sendo gerente — e tirar do caixa de uma loja quem segue operando na
 * loja do lado.
 */
class PdvOperatorProfile extends Model
{
    use HasTenant, HasUlid;

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    protected $table = 'pdv_operator_profiles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'username',
        'username_canonical',
        'status',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Grava as duas formas do username a partir de um valor cru.
     *
     * Único ponto de escrita: deixar o chamador preencher `username` e
     * `username_canonical` separadamente abriria a porta para as duas formas
     * divergirem, e a divergência quebraria a unicidade sem erro visível.
     */
    public function definirUsername(string $valor): void
    {
        $this->username = OperatorUsername::normalizar($valor);
        $this->username_canonical = OperatorUsername::canonical($valor);
    }

    /**
     * O perfil ATIVO correspondente a um username, dentro de um
     * estabelecimento informado explicitamente.
     *
     * A busca é pela forma canônica, então qualquer grafia equivalente encontra
     * a mesma pessoa. O tenant vem sempre de quem chama — no login, do Terminal
     * autenticado —, nunca de um espaço global: é isso que impede usar o
     * endpoint para descobrir se um username existe em outro cliente.
     */
    public static function ativoPorUsername(string $tenantId, string $username): ?self
    {
        $canonico = OperatorUsername::canonical($username);

        if ($canonico === '') {
            return null;
        }

        return self::consultaIsolada($tenantId)
            ->where('username_canonical', $canonico)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    public static function para(string $tenantId, string $userId): ?self
    {
        return self::consultaIsolada($tenantId)->where('user_id', $userId)->first();
    }

    /**
     * Consulta restrita a um estabelecimento informado explicitamente.
     *
     * Mesmo padrão da atribuição e da sessão: `withoutGlobalScopes()` remove o
     * TenantScope e o filtro volta no `where`, para valer também nos caminhos
     * que rodam sem contexto resolvido.
     *
     * @return Builder<self>
     */
    public static function consultaIsolada(string $tenantId): Builder
    {
        return self::withoutGlobalScopes()->where('tenant_id', $tenantId);
    }
}
