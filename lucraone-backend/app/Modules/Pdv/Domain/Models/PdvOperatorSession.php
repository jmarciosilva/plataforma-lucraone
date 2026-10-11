<?php

namespace App\Modules\Pdv\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O turno de caixa de uma pessoa num Terminal.
 *
 * Quatro estados, e a transição é sempre terminal: de ACTIVE sai-se para ENDED
 * (logout), EXPIRED (prazo) ou REVOKED (administração ou perda de autorização),
 * e de nenhum deles se volta. Reativar uma conta não ressuscita a sessão: o
 * caminho é novo login.
 *
 * O prazo é ABSOLUTO. `expires_at` é definido no login e nunca cresce —
 * revalidar confirma que as condições continuam valendo e move
 * `last_revalidated_at`, que é o marco da graça offline, não o da sessão.
 */
class PdvOperatorSession extends Model
{
    use HasTenant, HasUlid;

    /** Em uso. É o único estado que autentica. */
    public const STATUS_ACTIVE = 'ACTIVE';

    /** Encerrada pelo operador. */
    public const STATUS_ENDED = 'ENDED';

    /** Passou do prazo absoluto. */
    public const STATUS_EXPIRED = 'EXPIRED';

    /** Perdeu autorização, ou foi encerrada por administração. */
    public const STATUS_REVOKED = 'REVOKED';

    protected $table = 'pdv_operator_sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'branch_id',
        'terminal_id',
        'installation_id',
        'user_id',
        'machine_token_id',
        'credential_hash',
        'status',
        'authenticated_at',
        'last_revalidated_at',
        'expires_at',
        'ended_at',
        'revoked_at',
    ];

    /**
     * O hash nunca sai em serialização. Não é segredo — é derivado de um —, mas
     * publicá-lo permitiria confirmar a posse de uma credencial capturada.
     */
    protected $hidden = ['credential_hash'];

    protected $casts = [
        'authenticated_at' => 'datetime',
        'last_revalidated_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Está ativa E dentro do prazo?
     *
     * Duas perguntas separadas de propósito: uma sessão vencida cujo status
     * ainda diz ACTIVE existe entre o vencimento e a próxima requisição que a
     * reconcilia. A autenticação nunca aceita o status sozinho.
     */
    public function vigente(): bool
    {
        return $this->isActive() && $this->expires_at->isFuture();
    }

    /**
     * Consulta restrita a um estabelecimento informado explicitamente, sem
     * depender do contexto da requisição. Mesmo padrão da atribuição.
     *
     * @return Builder<self>
     */
    public static function consultaIsolada(string $tenantId): Builder
    {
        return self::withoutGlobalScopes()->where('tenant_id', $tenantId);
    }
}
