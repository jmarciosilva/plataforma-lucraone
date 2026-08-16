<?php

namespace App\Modules\Branches\Domain\Models;

use App\Modules\Companies\Domain\Models\Address;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory, HasTenant;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'company_id',
        'name',
        'code',
        'document_override',
        'email',
        'phone',
        'timezone',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Uma filial pertence a uma empresa.
     */
    public function company()
    {
        return $this->belongsTo(
            Company::class,
            'company_id',
            'id'
        );
    }

    /**
     * Uma filial possui muitos endereços.
     */
    public function addresses()
    {
        return $this->morphMany(
            Address::class,
            'addressable'
        );
    }

    /**
     * Endereço principal da filial.
     */
    public function primaryAddress()
    {
        return $this->morphOne(
            Address::class,
            'addressable'
        )->where('is_primary', true);
    }

    /**
     * Verifica se a filial está ativa.
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Obter empresa com escopo de tenant.
     *
     * Garante que a filial não consiga acessar empresa de outro tenant.
     */
    public function getCompanyAttribute()
    {
        return Company::where('id', $this->company_id)
            ->where('tenant_id', $this->tenant_id)
            ->first();
    }

    /**
     * Informar ao Laravel onde está a factory.
     */
    protected static function newFactory()
    {
        return BranchFactory::new();
    }
}
