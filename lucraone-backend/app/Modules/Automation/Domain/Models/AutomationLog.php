<?php

namespace App\Modules\Automation\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\AutomationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationLog extends Model
{
    use HasFactory, HasTenant, HasUlid;

    public const EXECUTADO = 'executed';

    public const IGNORADO = 'skipped';

    public const FALHOU = 'failed';

    public const ROTULOS = [
        self::EXECUTADO => 'executada',
        self::IGNORADO => 'ignorada',
        self::FALHOU => 'falhou',
    ];

    protected $fillable = [
        'tenant_id',
        'automation_rule_id',
        'trigger',
        'result',
        'action',
        'message',
        'payload',
        'outcome',
        'duration_ms',
        'ran_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'outcome' => 'array',
        'duration_ms' => 'integer',
        'ran_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return AutomationLogFactory::new();
    }

    public function rule()
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function scopeFailed($query)
    {
        return $query->where('result', self::FALHOU);
    }

    public function resultLabel(): string
    {
        return self::ROTULOS[$this->result] ?? $this->result;
    }
}
