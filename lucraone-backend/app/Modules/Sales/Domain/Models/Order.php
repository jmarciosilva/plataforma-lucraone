<?php

namespace App\Modules\Sales\Domain\Models;

use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Core\Domain\Traits\HasUlid;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Domain\Models\HasTenant;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasTenant, HasUlid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Transições permitidas do fluxo de venda.
     *
     * A reserva de estoque acontece na entrada em "confirmed" e é convertida em
     * saída definitiva em "shipped". Ver ADR-004.
     */
    public const TRANSICOES = [
        self::STATUS_DRAFT => [self::STATUS_PENDING, self::STATUS_CANCELLED],
        self::STATUS_PENDING => [self::STATUS_DRAFT, self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'customer_id',
        'user_id',
        'order_number',
        'status',
        'subtotal',
        'discount',
        'total',
        'currency',
        'notes',
        'confirmed_at',
        'shipped_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return OrderFactory::new();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    | Os escopos qualificam a coluna com a tabela porque relatórios fazem join
    | com customers, que também tem uma coluna status.
    */

    public function scopeOpen($query)
    {
        return $query->whereNotIn(
            $query->qualifyColumn('status'),
            [self::STATUS_COMPLETED, self::STATUS_CANCELLED]
        );
    }

    /**
     * Pedidos que contam como faturamento.
     *
     * São os que já saíram do estoque: enviados e concluídos. Definir isso em um
     * lugar só evita que a tela de vendas e os relatórios discordem sobre quanto
     * o estabelecimento vendeu.
     */
    public function scopeRevenue($query)
    {
        return $query->whereIn(
            $query->qualifyColumn('status'),
            [self::STATUS_SHIPPED, self::STATUS_COMPLETED]
        );
    }

    /**
     * Carteira em aberto: pedidos que ainda não viraram faturamento e não foram
     * cancelados.
     *
     * Não é o complemento de `open()` — aquele ainda inclui enviado, que já é
     * receita e seria contado duas vezes.
     */
    public function scopeBacklog($query)
    {
        return $query->whereIn(
            $query->qualifyColumn('status'),
            [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_CONFIRMED]
        );
    }

    /**
     * O pedido ainda pode receber, remover ou alterar itens?
     *
     * Depois de confirmado o estoque já está reservado contra as quantidades
     * atuais, então mexer nos itens sairia do sincronismo.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING], true);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSICOES[$this->status] ?? [], true);
    }

    /**
     * Estoque está reservado enquanto o pedido estiver confirmado.
     */
    public function holdsReservation(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');

        $this->forceFill([
            'subtotal' => $subtotal,
            'total' => max(0, (float) $subtotal - (float) $this->discount),
        ])->save();
    }
}
