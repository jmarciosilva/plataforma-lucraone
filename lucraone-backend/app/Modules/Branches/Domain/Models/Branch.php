<?php

namespace App\Modules\Branches\Domain\Models;

use App\Modules\Companies\Domain\Models\Address;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use App\Modules\Terminals\Domain\Models\Terminal;
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
        'is_headquarters',
        'max_pdv_terminals',
    ];

    protected $casts = [
        'is_headquarters' => 'boolean',
        'max_pdv_terminals' => 'integer',
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

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isHeadquarters(): bool
    {
        return (bool) $this->is_headquarters;
    }

    public function scopeHeadquarters($query)
    {
        return $query->where('is_headquarters', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
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

    public function terminals()
    {
        return $this->hasMany(Terminal::class, 'branch_id', 'id');
    }

    /**
     * Informar ao Laravel onde está a factory.
     */
    protected static function newFactory()
    {
        return BranchFactory::new();
    }
}
