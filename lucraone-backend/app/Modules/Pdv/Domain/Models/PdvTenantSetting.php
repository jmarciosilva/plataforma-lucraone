<?php

namespace App\Modules\Pdv\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Política operacional do PDV de um estabelecimento.
 *
 * Ausência de linha é estado válido e significa "tudo no padrão". É por isso que
 * a leitura passa por `para()`, que devolve um objeto não persistido em vez de
 * null: quem consulta a política não deveria precisar saber se alguém já abriu a
 * tela de configuração.
 */
class PdvTenantSetting extends Model
{
    use HasTenant, HasUlid;

    protected $table = 'pdv_tenant_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'tenant_id', 'screen_lock_minutes'];

    protected $casts = [
        'screen_lock_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * A política do estabelecimento, persistida ou padrão.
     */
    public static function para(string $tenantId): self
    {
        $linha = self::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        return $linha instanceof self
            ? $linha
            : new self(['tenant_id' => $tenantId, 'screen_lock_minutes' => null]);
    }

    /**
     * Minutos de inatividade antes do bloqueio de tela, ou null se desligado.
     *
     * Publicado no contrato e aplicado pelo CLIENTE. O backend não bloqueia
     * tela — ele não tem tela —, e bloqueio de tela não encerra sessão nem
     * fecha caixa.
     */
    public function bloqueioDeTela(): ?int
    {
        return $this->screen_lock_minutes !== null && $this->screen_lock_minutes > 0
            ? $this->screen_lock_minutes
            : null;
    }
}
