<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Pdv\Domain\Models\PdvReceivedSale;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recebe e persiste uma venda enviada pelo PDV, de forma idempotente.
 *
 * Idempotência pelo `idempotency_key`: o PDV reenvia o mesmo evento até receber
 * 2xx/409. O mesmo key NUNCA gera dois registros. A checagem prévia resolve o
 * caso comum; a constraint UNIQUE fecha a janela de corrida entre dois reenvios
 * simultâneos, convertida aqui em "duplicado" em vez de erro 500.
 *
 * Os vínculos (tenant/empresa/filial/terminal) vêm do Terminal autenticado,
 * não do corpo — um cliente de máquina não escolhe de quem é a venda.
 */
class ReceivePdvSale
{
    public function __construct(
        private AplicarVendaNoEstoque $estoque,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  payload já validado
     */
    public function receive(Terminal $terminal, array $dados): PdvSaleIngestionResult
    {
        $key = (string) $dados['idempotency_key'];

        $existente = PdvReceivedSale::query()->where('idempotency_key', $key)->first();
        if ($existente !== null) {
            return new PdvSaleIngestionResult($existente->sale_id, $existente->received_at, true);
        }

        try {
            $venda = DB::transaction(fn () => $this->gravar($terminal, $dados, $key));
        } catch (UniqueConstraintViolationException) {
            // Corrida: outro reenvio do MESMO key gravou primeiro. É sucesso
            // idempotente, não falha — devolve 409 como qualquer duplicado.
            $venda = PdvReceivedSale::query()->where('idempotency_key', $key)->firstOrFail();

            return new PdvSaleIngestionResult($venda->sale_id, $venda->received_at, true);
        }

        // Efeito no estoque DEPOIS de a venda estar persistida, e isolado dela:
        // a venda já foi aceita (contrato com o PDV), então uma falha de estoque
        // nunca pode desfazê-la nem recusar a resposta de sucesso. Roda uma vez
        // por venda nova — reenvios caem no curto-circuito de idempotência acima.
        try {
            $this->estoque->aplicar($venda->load('items'));
        } catch (Throwable $e) {
            Log::warning('Venda PDV: falha ao aplicar estoque da venda.', [
                'sale_id' => $venda->sale_id,
                'exception' => $e::class,
            ]);
        }

        return new PdvSaleIngestionResult($venda->sale_id, $venda->received_at, false);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function gravar(Terminal $terminal, array $dados, string $key): PdvReceivedSale
    {
        $pagamento = $dados['payment'] ?? [];
        $recebidoEm = now();

        $venda = PdvReceivedSale::query()->create([
            'id' => (string) Str::ulid(),
            'idempotency_key' => $key,
            'event_type' => $dados['event_type'],
            'payload_version' => (int) $dados['payload_version'],
            'sale_id' => (string) $dados['sale_id'],

            // Autoridade do Terminal autenticado — nunca do corpo.
            'tenant_id' => $terminal->tenant_id,
            'company_id' => $terminal->company_id,
            'branch_id' => $terminal->branch_id,
            'terminal_id' => $terminal->id,

            'operator_id' => $dados['operator_id'] ?? null,
            'operator_name' => $dados['operator_name'] ?? null,
            'currency' => strtoupper((string) $dados['currency']),

            'subtotal_amount' => $dados['subtotal_amount'],
            'discount_amount' => $dados['discount_amount'],
            'total_amount' => $dados['total_amount'],

            'payment_method' => $pagamento['method'] ?? null,
            'payment_amount' => $pagamento['payment_amount'] ?? null,
            'change_amount' => $pagamento['change_amount'] ?? null,
            'payment_declaration' => $pagamento['declaration'] ?? null,

            'confirmed_at' => $dados['confirmed_at'] ?? null,
            'cancelled_at' => $dados['cancelled_at'] ?? null,
            'cancellation_reason' => $dados['cancellation_reason'] ?? null,
            'created_at' => $dados['created_at'] ?? $recebidoEm,
            'received_at' => $recebidoEm,
        ]);

        foreach ($dados['items'] as $item) {
            $venda->items()->create([
                'line_number' => (int) $item['line_number'],
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'sku' => $item['sku'] ?? null,
                'unit' => $item['unit'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? '0',
                'total_amount' => $item['total_amount'],
            ]);
        }

        foreach (array_values(Arr::get($dados, 'payment.entries', [])) as $i => $entry) {
            $venda->paymentEntries()->create([
                'seq' => $i + 1,
                'method' => $entry['method'],
                'amount' => $entry['amount'],
            ]);
        }

        return $venda;
    }
}
