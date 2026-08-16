<?php

namespace App\Modules\Companies\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Events\CompanyCreated;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, HasTenant;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $dispatchesEvents = [
        'created' => CompanyCreated::class,
    ];

    protected $fillable = [
        'id',
        'tenant_id',
        'legal_name',
        'trade_name',
        'document',
        'state_registration',
        'municipal_registration',
        'email',
        'phone',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Uma empresa possui muitas filiais.
     */
    public function branches()
    {
        return $this->hasMany(
            Branch::class,
            'company_id',
            'id'
        );
    }

    /**
     * Uma empresa possui muitos endereços.
     */
    public function addresses()
    {
        return $this->morphMany(
            Address::class,
            'addressable'
        );
    }

    /**
     * Endereço principal da empresa.
     */
    public function primaryAddress()
    {
        return $this->morphOne(
            Address::class,
            'addressable'
        )->where('is_primary', true);
    }

    /**
     * Verifica se a empresa está ativa.
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Informar ao Laravel onde está a factory.
     */
    protected static function newFactory()
    {
        return CompanyFactory::new();
    }
}
