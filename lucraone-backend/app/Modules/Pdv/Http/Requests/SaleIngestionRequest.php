<?php

namespace App\Modules\Pdv\Http\Requests;

use App\Modules\Pdv\Domain\Models\PdvReceivedSale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do evento de venda enviado pelo PDV (POST /pdv/sales).
 *
 * O sujeito-máquina e seu alcance já foram provados pelos middlewares
 * (`auth:sanctum`, `terminal.context`, `machine.ability:pdv:sales:write`), então
 * `authorize()` é `true`: aqui só se valida a FORMA do corpo.
 *
 * Campos desconhecidos são IGNORADOS, não recusados — o payload pode crescer em
 * versões futuras e o `payload_version` é o que governa a leitura. tenant_id,
 * company_id, branch_id e terminal_id podem vir no corpo, mas não são validados
 * nem usados: a autoridade vem do Terminal autenticado, nunca do cliente.
 *
 * Valores monetários são strings decimais com ponto ("52.80"), como o PDV envia.
 */
class SaleIngestionRequest extends FormRequest
{
    private const MONEY = 'regex:/^\d{1,10}(?:\.\d{1,2})?$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:64'],
            'event_type' => ['required', Rule::in([PdvReceivedSale::EVENT_CONFIRMED, PdvReceivedSale::EVENT_CANCELLED])],
            'payload_version' => ['required', 'integer', 'in:1'],
            'sale_id' => ['required', 'string', 'max:64'],

            'operator_id' => ['nullable', 'string', 'max:64'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],

            'subtotal_amount' => ['required', 'string', self::MONEY],
            'discount_amount' => ['required', 'string', self::MONEY],
            'total_amount' => ['required', 'string', self::MONEY],

            'payment' => ['required', 'array'],
            'payment.method' => ['required', Rule::in(PdvReceivedSale::PAYMENT_METHODS)],
            'payment.payment_amount' => ['required', 'string', self::MONEY],
            'payment.change_amount' => ['nullable', 'string', self::MONEY],
            'payment.declaration' => ['nullable', 'string', 'max:20'],
            'payment.entries' => ['sometimes', 'array'],
            'payment.entries.*.method' => ['required_with:payment.entries', Rule::in(PdvReceivedSale::PAYMENT_METHODS)],
            'payment.entries.*.amount' => ['required_with:payment.entries', 'string', self::MONEY],

            'items' => ['required', 'array', 'min:1'],
            'items.*.line_number' => ['required', 'integer', 'min:1'],
            'items.*.product_id' => ['nullable', 'string', 'max:64'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:64'],
            'items.*.unit' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(?:\.\d{1,4})?$/'],
            'items.*.unit_price' => ['required', 'string', self::MONEY],
            'items.*.discount_amount' => ['nullable', 'string', self::MONEY],
            'items.*.total_amount' => ['required', 'string', self::MONEY],

            'confirmed_at' => ['nullable', 'date'],
            'cancelled_at' => ['required_if:event_type,'.PdvReceivedSale::EVENT_CANCELLED, 'nullable', 'date'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
            'created_at' => ['nullable', 'date'],
        ];
    }
}
