<?php

namespace App\Modules\Terminals\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Terminals\Domain\TerminalAssignmentValidator;
use Database\Factories\TerminalFactory;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use LogicException;

/**
 * Terminal de PDV: sujeito de máquina, nunca uma pessoa.
 *
 * Implementa Authenticatable porque o Sanctum devolve o tokenable como
 * `$request->user()` e o contrato do guard exige esse tipo — `GuardHelpers::setUser()`
 * o declara, e é por ele que `Sanctum::actingAs()` passa. O trait HasApiTokens
 * sozinho não bastaria: ele dá tokens()/createToken()/tokenCan(), não identidade.
 *
 * O que este model deliberadamente NÃO tem é qualquer caminho de credencial
 * humana. Não há password, remember_token, e-mail, login ou provider: os
 * métodos do contrato que tratam de senha lançam exceção em vez de devolver
 * um valor vazio, porque um Terminal chegando a um fluxo de senha é um erro de
 * programação, não um caso a tolerar silenciosamente. Quem prova posse é a
 * credencial de máquina; o installation_id é identificador público.
 */
class Terminal extends Model implements AuthenticatableContract
{
    use HasApiTokens, HasFactory, HasTenant, HasUlid;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_BLOCKED = 'BLOCKED';

    public const STATUS_REVOKED = 'REVOKED';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_BLOCKED, self::STATUS_REVOKED];

    protected $fillable = ['tenant_id', 'company_id', 'branch_id', 'installation_id', 'name', 'status'];

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected $casts = ['id' => 'string', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(fn (Terminal $terminal) => app(TerminalAssignmentValidator::class)->validate($terminal));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function pairingCodes(): HasMany
    {
        return $this->hasMany(TerminalPairingCode::class);
    }

    protected static function newFactory(): TerminalFactory
    {
        return TerminalFactory::new();
    }

    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    public function getAuthIdentifier(): string
    {
        return $this->getKey();
    }

    /**
     * Terminal não tem senha. Chegar aqui significa que algum fluxo humano o
     * tratou como conta de pessoa; falhar alto é mais seguro que devolver uma
     * string vazia e deixar um Hash::check decidir.
     */
    public function getAuthPasswordName(): string
    {
        throw new LogicException('Terminal has no password; machine authentication uses a machine credential.');
    }

    public function getAuthPassword(): string
    {
        throw new LogicException('Terminal has no password; machine authentication uses a machine credential.');
    }

    /**
     * Devolver null é como o framework reconhece que "lembrar-me" não existe
     * para este sujeito: o SessionGuard consulta o nome da coluna antes de
     * tentar gravá-la.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Sem persistência: sessão humana não se aplica a Terminal.
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
