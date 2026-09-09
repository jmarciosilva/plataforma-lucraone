<?php

namespace App\Modules\Automation\Domain\Models;

use App\Modules\Automation\Domain\TriggerCatalog;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    use HasFactory, HasTenant, HasUlid;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'trigger',
        'conditions',
        'action',
        'action_config',
        'active',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'action_config' => 'array',
        'active' => 'boolean',
        'last_run_at' => 'datetime',
        'run_count' => 'integer',
    ];

    protected static function newFactory()
    {
        return AutomationRuleFactory::new();
    }

    public function logs()
    {
        return $this->hasMany(AutomationLog::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('trigger', $trigger);
    }

    public function triggerLabel(): string
    {
        return TriggerCatalog::rotulo($this->trigger);
    }

    /**
     * Registra que a regra rodou. Usa update direto para não disparar eventos
     * de model nem mexer em updated_at do conteúdo da regra.
     */
    public function registrarExecucao(): void
    {
        $this->forceFill([
            'last_run_at' => now(),
            'run_count' => $this->run_count + 1,
        ])->saveQuietly();
    }
}
