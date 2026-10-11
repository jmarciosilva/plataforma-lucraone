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
 * Três marcos de tempo, e cada um responde uma pergunta diferente:
 *
 *   - `expires_at` é a validade TÉCNICA da credencial, DESLIZANTE: avança a
 *     cada uso autenticado legítimo. É o que impede uma credencial eterna sem
 *     impedir uma jornada longa;
 *   - `absolute_expires_at` é o teto da JORNADA, opcional. NULL significa sem
 *     teto, que é a política do piloto: o comerciante fica no caixa o dia
 *     inteiro. Nenhum deslizamento passa dele quando existe;
 *   - `last_revalidated_at` é o marco da graça OFFLINE, movido somente por
 *     revalidação online explícita — nunca por uso comum.
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
        'machine_rotations',
        'credential_hash',
        'status',
        'authenticated_at',
        'last_revalidated_at',
        'expires_at',
        'absolute_expires_at',
        'ended_at',
        'revoked_at',
    ];

    /**
     * O hash nunca sai em serialização. Não é segredo — é derivado de um —, mas
     * publicá-lo permitiria confirmar a posse de uma credencial capturada.
     */
    protected $hidden = ['credential_hash'];

    protected $casts = [
        'machine_rotations' => 'integer',
        'authenticated_at' => 'datetime',
        'last_revalidated_at' => 'datetime',
        'expires_at' => 'datetime',
        'absolute_expires_at' => 'datetime',
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
     * Está ativa E dentro dos dois prazos?
     *
     * Duas perguntas separadas de propósito: uma sessão vencida cujo status
     * ainda diz ACTIVE existe entre o vencimento e a próxima requisição que a
     * reconcilia. A autenticação nunca aceita o status sozinho.
     */
    public function vigente(): bool
    {
        return $this->isActive()
            && $this->expires_at->isFuture()
            && ! $this->jornadaEncerrada();
    }

    /**
     * A jornada estourou o teto absoluto?
     *
     * Sem teto configurado (`absolute_expires_at` nulo) a resposta é sempre
     * não: é essa a política que permite o turno de doze ou quinze horas.
     */
    public function jornadaEncerrada(): bool
    {
        return $this->absolute_expires_at !== null && $this->absolute_expires_at->isPast();
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
