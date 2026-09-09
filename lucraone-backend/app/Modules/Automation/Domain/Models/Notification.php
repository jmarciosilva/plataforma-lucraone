<?php

namespace App\Modules\Automation\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Aviso interno do painel.
 *
 * Com `user_id` nulo o aviso é do estabelecimento inteiro — é o caso das
 * automações, que avisam a operação, não uma pessoa específica.
 */
class Notification extends Model
{
    use HasFactory, HasTenant, HasUlid;

    public const NIVEL_INFO = 'info';

    public const NIVEL_SUCESSO = 'sucesso';

    public const NIVEL_ATENCAO = 'atencao';

    public const NIVEL_ERRO = 'erro';

    public const NIVEIS = [
        self::NIVEL_INFO => 'informação',
        self::NIVEL_SUCESSO => 'sucesso',
        self::NIVEL_ATENCAO => 'atenção',
        self::NIVEL_ERRO => 'erro',
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'message',
        'level',
        'link',
        'automation_rule_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return NotificationFactory::new();
    }

    public function rule()
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Avisos que uma pessoa vê: os dela e os do estabelecimento.
     */
    public function scopeVisibleTo($query, string $userId)
    {
        return $query->where(function ($query) use ($userId) {
            $query->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function levelLabel(): string
    {
        return self::NIVEIS[$this->level] ?? $this->level;
    }
}
