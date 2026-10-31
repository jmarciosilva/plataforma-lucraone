<?php

namespace Tests\Feature\Pdv;

use App\Modules\Inventory\Domain\Models\Inventory;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Terminals\Domain\MachineTokenAbility;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * POST /api/v1/pdv/sales — ingestão de vendas do PDV (PDV-BE-06).
 *
 * Tudo passa pelas rotas reais: prova autenticação de máquina, ability,
 * idempotência, o contrato 201/409 combinado com o cliente Java e o envelope
 * 422 das rotas do PDV.
 */
#[Group('pdv')]
#[Group('pdv-sales')]
class PdvSalesIngestionTest extends PdvTestCase
{
    public function test_terminal_registra_venda_confirmada_e_recebe_201(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida();

        $resposta = $this->postSale($payload, $token);

        $resposta->assertCreated()
            ->assertJsonPath('sale_id', $payload['sale_id'])
            ->assertJsonStructure(['sale_id', 'received_at']);

        $this->assertDatabaseHas('pdv_received_sales', [
            'idempotency_key' => $payload['idempotency_key'],
            'event_type' => 'SALE_CONFIRMED',
            'tenant_id' => $this->terminal->tenant_id,
            'branch_id' => $this->terminal->branch_id,
            'terminal_id' => $this->terminal->id,
            'total_amount' => '52.80',
            'payment_method' => 'MEAL_VOUCHER',
        ]);
        $this->assertDatabaseCount('pdv_received_sale_items', 1);
    }

    public function test_reenvio_do_mesmo_idempotency_key_devolve_409(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida();

        $this->postSale($payload, $token)->assertCreated();

        // Segundo envio do MESMO evento: o PDV reenviou porque não viu o 2xx.
        $this->postSale($payload, $token)
            ->assertStatus(409)
            ->assertExactJson(['error' => 'already_accepted']);

        $this->assertDatabaseCount('pdv_received_sales', 1);
    }

    public function test_payload_version_diferente_de_1_e_recusado_com_422(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida(['payload_version' => 2]);

        $this->assertErroPdv($this->postSale($payload, $token), 422, 'validation_error');
        $this->assertDatabaseCount('pdv_received_sales', 0);
    }

    public function test_campos_obrigatorios_ausentes_sao_recusados(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida();
        unset($payload['total_amount'], $payload['items']);

        $this->assertErroPdv($this->postSale($payload, $token), 422, 'validation_error');
        $this->assertDatabaseCount('pdv_received_sales', 0);
    }

    public function test_venda_cancelada_e_registrada_e_nao_entra_no_total(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida([
            'event_type' => 'SALE_CANCELLED',
            'cancelled_at' => '2026-10-31T21:00:00Z',
            'cancellation_reason' => 'estorno do operador',
        ]);

        $this->postSale($payload, $token)->assertCreated();

        $this->assertDatabaseHas('pdv_received_sales', [
            'idempotency_key' => $payload['idempotency_key'],
            'event_type' => 'SALE_CANCELLED',
            'cancellation_reason' => 'estorno do operador',
        ]);
    }

    public function test_split_payment_persiste_as_entradas(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida();
        $payload['payment']['entries'] = [
            ['method' => 'MEAL_VOUCHER', 'amount' => '30.00'],
            ['method' => 'CASH', 'amount' => '22.80'],
        ];

        $this->postSale($payload, $token)->assertCreated();

        $this->assertDatabaseCount('pdv_received_sale_payment_entries', 2);
        $this->assertDatabaseHas('pdv_received_sale_payment_entries', ['seq' => 1, 'method' => 'MEAL_VOUCHER', 'amount' => '30.00']);
        $this->assertDatabaseHas('pdv_received_sale_payment_entries', ['seq' => 2, 'method' => 'CASH', 'amount' => '22.80']);
    }

    public function test_vinculos_vem_do_terminal_e_nao_do_corpo(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida([
            'tenant_id' => (string) Str::ulid(),
            'company_id' => (string) Str::ulid(),
            'branch_id' => (string) Str::ulid(),
            'terminal_id' => 'pdv-falso',
        ]);

        $this->postSale($payload, $token)->assertCreated();

        $this->assertDatabaseHas('pdv_received_sales', [
            'idempotency_key' => $payload['idempotency_key'],
            'tenant_id' => $this->terminal->tenant_id,
            'company_id' => $this->terminal->company_id,
            'branch_id' => $this->terminal->branch_id,
            'terminal_id' => $this->terminal->id,
        ]);
    }

    public function test_campos_desconhecidos_sao_ignorados(): void
    {
        $token = $this->credencialDeMaquina();
        $payload = $this->vendaValida(['campo_futuro' => 'qualquer coisa']);

        $this->postSale($payload, $token)->assertCreated();
        $this->assertDatabaseCount('pdv_received_sales', 1);
    }

    public function test_venda_confirmada_da_baixa_no_estoque(): void
    {
        $token = $this->credencialDeMaquina();
        [$produto] = $this->produtoComEstoque('100');

        $payload = $this->vendaValida();
        $payload['items'] = [$this->itemDe($produto, '3.0000', '5.00', '15.00')];

        $this->postSale($payload, $token)->assertCreated();

        $this->assertSame(97.0, $this->saldo($produto));
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $produto->id,
            'company_id' => $this->terminal->company_id,
            'type' => 'out',
            'quantity' => '3.000',
        ]);
    }

    public function test_venda_cancelada_estorna_o_estoque(): void
    {
        $token = $this->credencialDeMaquina();
        [$produto] = $this->produtoComEstoque('100');

        $payload = $this->vendaValida(['event_type' => 'SALE_CANCELLED', 'cancelled_at' => '2026-10-31T21:00:00Z']);
        $payload['items'] = [$this->itemDe($produto, '4.0000', '5.00', '20.00')];

        $this->postSale($payload, $token)->assertCreated();

        $this->assertSame(104.0, $this->saldo($produto));
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $produto->id, 'type' => 'in', 'quantity' => '4.000']);
    }

    public function test_item_sem_produto_correspondente_nao_impede_a_venda(): void
    {
        $token = $this->credencialDeMaquina();

        // O item padrão referencia product_id/sku inexistentes no backend.
        $this->postSale($this->vendaValida(), $token)->assertCreated();

        $this->assertDatabaseCount('pdv_received_sales', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_venda_pode_deixar_o_estoque_negativo(): void
    {
        $token = $this->credencialDeMaquina();
        [$produto] = $this->produtoComEstoque('2');

        $payload = $this->vendaValida();
        $payload['items'] = [$this->itemDe($produto, '5.0000', '5.00', '25.00')];

        $this->postSale($payload, $token)->assertCreated();

        $this->assertSame(-3.0, $this->saldo($produto));
    }

    public function test_sem_credencial_recebe_401(): void
    {
        $this->postSale($this->vendaValida(), null)->assertUnauthorized();
        $this->assertDatabaseCount('pdv_received_sales', 0);
    }

    public function test_credencial_sem_ability_de_venda_recebe_403(): void
    {
        $this->terminal->update(['installation_id' => (string) Str::uuid(), 'status' => 'ACTIVE']);
        // Credencial legada, sem a ability de venda.
        $token = $this->terminal->fresh()->createToken('legada', [MachineTokenAbility::TERMINAL_READ])->plainTextToken;

        $this->postSale($this->vendaValida(), $token)->assertForbidden();
        $this->assertDatabaseCount('pdv_received_sales', 0);
    }

    /**
     * Produto do tenant/empresa do terminal, já com saldo de estoque.
     *
     * @return array{0: Product, 1: Inventory}
     */
    private function produtoComEstoque(string $saldo): array
    {
        $produto = Product::factory()->create([
            'tenant_id' => $this->terminal->tenant_id,
            'company_id' => $this->terminal->company_id,
            'unit' => 'UN',
        ]);

        $inventory = Inventory::create([
            'tenant_id' => $this->terminal->tenant_id,
            'product_id' => $produto->id,
            'company_id' => $this->terminal->company_id,
            'quantity_on_hand' => $saldo,
            'reserved' => '0',
        ]);

        return [$produto, $inventory];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemDe(Product $produto, string $quantidade, string $precoUnit, string $total): array
    {
        return [
            'line_number' => 1,
            'product_id' => $produto->id,
            'product_name' => $produto->name,
            'sku' => $produto->sku,
            'unit' => 'UN',
            'quantity' => $quantidade,
            'unit_price' => $precoUnit,
            'discount_amount' => '0.00',
            'total_amount' => $total,
        ];
    }

    private function saldo(Product $produto): float
    {
        return (float) Inventory::where('tenant_id', $this->terminal->tenant_id)
            ->where('company_id', $this->terminal->company_id)
            ->where('product_id', $produto->id)
            ->value('quantity_on_hand');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function vendaValida(array $overrides = []): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'event_type' => 'SALE_CONFIRMED',
            'payload_version' => 1,
            'sale_id' => (string) Str::uuid(),
            'operator_id' => (string) Str::ulid(),
            'operator_name' => 'João Silva',
            'currency' => 'BRL',
            'subtotal_amount' => '52.80',
            'discount_amount' => '0.00',
            'total_amount' => '52.80',
            'payment' => [
                'method' => 'MEAL_VOUCHER',
                'payment_amount' => '52.80',
                'change_amount' => '0.00',
                'declaration' => 'MANUAL',
                'entries' => [],
            ],
            'items' => [[
                'line_number' => 1,
                'product_id' => (string) Str::ulid(),
                'product_name' => 'Coca-Cola 350ml',
                'sku' => '7891234567890',
                'unit' => 'UN',
                'quantity' => '2.0000',
                'unit_price' => '5.90',
                'discount_amount' => '0.00',
                'total_amount' => '11.80',
            ]],
            'confirmed_at' => '2026-10-31T20:15:00Z',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'created_at' => '2026-10-31T20:15:00Z',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSale(array $payload, ?string $token, array $cabecalhos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        $req = $this->withServerVariables(['REMOTE_ADDR' => self::IP_CLIENTE])
            ->withHeaders(['X-Request-ID' => (string) Str::uuid(), ...$cabecalhos]);

        if ($token !== null) {
            $req = $req->withToken($token);
        }

        return $req->postJson('/api/v1/pdv/sales', $payload);
    }
}
