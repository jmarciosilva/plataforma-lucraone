<?php

namespace App\Modules\Pdv\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venda recebida do PDV. Cabeçalho + itens + entradas de pagamento.
 *
 * `$timestamps = false` de propósito: `created_at` é a criação da venda NO PDV
 * (vem do payload) e `received_at` é o aceite da retaguarda. Nenhum dos dois é
 * o `updated_at` gerenciado do Eloquent, e a venda recebida é imutável — não há
 * atualização a carimbar.
 */
class PdvReceivedSale extends Model
{
    public const EVENT_CONFIRMED = 'SALE_CONFIRMED';

    public const EVENT_CANCELLED = 'SALE_CANCELLED';

    /** Nomes amigáveis das formas de pagamento, para a tela de retaguarda. */
    public const PAYMENT_LABELS = [
        'CASH' => 'Dinheiro',
        'CARD_DEBIT' => 'Cartão Débito',
        'CARD_CREDIT' => 'Cartão Crédito',
        'PIX' => 'Pix',
        'MEAL_VOUCHER' => 'Ticket Refeição',
        'FOOD_VOUCHER' => 'Ticket Alimentação',
    ];

    public const PAYMENT_METHODS = ['CASH', 'CARD_DEBIT', 'CARD_CREDIT', 'PIX', 'MEAL_VOUCHER', 'FOOD_VOUCHER'];

    protected $table = 'pdv_received_sales';

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'idempotency_key', 'event_type', 'payload_version', 'sale_id',
        'tenant_id', 'company_id', 'branch_id', 'terminal_id',
        'operator_id', 'operator_name', 'currency',
        'subtotal_amount', 'discount_amount', 'total_amount',
        'payment_method', 'payment_amount', 'change_amount', 'payment_declaration',
        'confirmed_at', 'cancelled_at', 'cancellation_reason', 'created_at', 'received_at',
    ];

    protected $casts = [
        'payload_version' => 'integer',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PdvReceivedSaleItem::class, 'sale_id');
    }

    public function paymentEntries(): HasMany
    {
        return $this->hasMany(PdvReceivedSalePaymentEntry::class, 'sale_id');
    }

    public function isCancelled(): bool
    {
        return $this->event_type === self::EVENT_CANCELLED;
    }

    public static function paymentLabel(?string $method): string
    {
        return self::PAYMENT_LABELS[$method] ?? ($method ?? '—');
    }
}
