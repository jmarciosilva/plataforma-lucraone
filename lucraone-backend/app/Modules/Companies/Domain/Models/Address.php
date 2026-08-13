<?php

namespace App\Modules\Companies\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Tenancy\Domain\Models\HasTenant;

/**
 * Modelo polimórfico para endereços reutilizável em múltiplas entidades.
 *
 * Pode ser associado a:
 * - Company (escritório da empresa)
 * - Branch (endereço da filial)
 * - User (futuro: endereço do usuário)
 * - Customer (futuro: endereço do cliente)
 */
class Address extends Model
{
    use HasFactory, HasTenant;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'addressable_type',
        'addressable_id',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'state',
        'postal_code',
        'country',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Endereço pertence a um modelo polimórfico.
     */
    public function addressable()
    {
        return $this->morphTo();
    }

    /**
     * Formatar endereço para exibição.
     */
    public function formatted(): string
    {
        $parts = [
            $this->street,
            $this->number,
        ];

        if ($this->complement) {
            $parts[] = $this->complement;
        }

        $parts[] = $this->district;
        $parts[] = $this->city . ' - ' . $this->state;
        $parts[] = 'CEP: ' . $this->postal_code;

        return implode(', ', $parts);
    }

    /**
     * Informar ao Laravel onde está a factory.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AddressFactory::new();
    }
}
