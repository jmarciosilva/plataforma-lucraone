<?php

namespace Tests\Feature\Security;

use App\Modules\Products\Domain\Models\Price;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Sales\Domain\Models\Customer;
use App\Modules\Sales\Domain\Models\Order;

/**
 * SEC-01 — autorização das 8 rotas do módulo Sales.
 *
 * OrderController (5) e CustomerController (3).
 *
 * Mudança de status usa `OrderPolicy::update` e o cancelamento usa
 * `OrderPolicy::delete`, exatamente como `OrderWebController`. Nada da
 * semântica comercial de PM-04B/PM-04C é tocado aqui: os testes só exercitam
 * quem pode chamar.
 */
class ApiAuthorizationSalesTest extends ApiAuthorizationTestCase
{
    private function produtoComPreco(): Product
    {
        $produto = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => 'SEC01-SALES-'.fake()->unique()->numerify('####'),
            'status' => 'active',
            'unit' => 'UN',
        ]);

        Price::factory()->sale()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $produto->id,
            'amount' => 25.00,
            'currency' => 'BRL',
        ]);

        return $produto;
    }

    private function pedido(string $status = Order::STATUS_DRAFT): Order
    {
        return Order::factory()->forCompany($this->company)->status($status)->create();
    }

    private function cliente(): Customer
    {
        return Customer::factory()->forCompany($this->company)->create();
    }

    // ---------------------------------------------------------------
    // Orders — escrita barrada
    // ---------------------------------------------------------------

    public function test_leitor_nao_cria_pedido(): void
    {
        $produto = $this->produtoComPreco();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->postJson('/api/v1/orders', [
                'company_id' => $this->company->id,
                'items' => [
                    ['product_id' => (string) $produto->id, 'quantity' => '2'],
                ],
            ])
        );

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_leitor_nao_muda_status_do_pedido(): void
    {
        $pedido = $this->pedido();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->putJson("/api/v1/orders/{$pedido->id}/status", [
                'status' => Order::STATUS_PENDING,
            ])
        );

        $this->assertSame(Order::STATUS_DRAFT, $pedido->fresh()->status);
    }

    public function test_leitor_nao_cancela_pedido(): void
    {
        $pedido = $this->pedido();

        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->deleteJson("/api/v1/orders/{$pedido->id}")
        );

        $this->assertSame(Order::STATUS_DRAFT, $pedido->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Orders — leitura liberada
    // ---------------------------------------------------------------

    public function test_leitor_le_pedidos(): void
    {
        $pedido = $this->pedido();

        $this->comoLeitor()->getJson('/api/v1/orders')->assertOk();
        $this->comoLeitor()->getJson("/api/v1/orders/{$pedido->id}")->assertOk();
    }

    // ---------------------------------------------------------------
    // Orders — caminho positivo
    // ---------------------------------------------------------------

    public function test_gerente_cria_muda_status_e_cancela_pedido(): void
    {
        $produto = $this->produtoComPreco();

        $criado = $this->comoGerente()->postJson('/api/v1/orders', [
            'company_id' => $this->company->id,
            'items' => [
                ['product_id' => (string) $produto->id, 'quantity' => '2'],
            ],
        ])->assertCreated();

        $id = $criado->json('data.id');

        $this->comoGerente()
            ->putJson("/api/v1/orders/{$id}/status", ['status' => Order::STATUS_PENDING])
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_PENDING);

        $this->comoGerente()
            ->deleteJson("/api/v1/orders/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', Order::STATUS_CANCELLED);
    }

    // ---------------------------------------------------------------
    // Customers
    // ---------------------------------------------------------------

    public function test_leitor_nao_cria_cliente(): void
    {
        $this->assertNegadoPorAutorizacao(
            $this->comoLeitor()->postJson('/api/v1/customers', [
                'company_id' => $this->company->id,
                'name' => 'Cliente Proibido',
            ])
        );

        $this->assertDatabaseMissing('customers', ['name' => 'Cliente Proibido']);
    }

    public function test_leitor_le_clientes(): void
    {
        $cliente = $this->cliente();

        $this->comoLeitor()->getJson('/api/v1/customers')->assertOk();
        $this->comoLeitor()->getJson("/api/v1/customers/{$cliente->id}")->assertOk();
    }

    public function test_gerente_cria_cliente(): void
    {
        $this->comoGerente()->postJson('/api/v1/customers', [
            'company_id' => $this->company->id,
            'name' => 'Cliente Permitido',
        ])->assertCreated();

        $this->assertDatabaseHas('customers', ['name' => 'Cliente Permitido']);
    }

    // ---------------------------------------------------------------
    // SEC-04 — coringa
    // ---------------------------------------------------------------

    public function test_create_role_nao_autoriza_sales(): void
    {
        $pedido = $this->pedido();

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->getJson('/api/v1/orders')
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->deleteJson("/api/v1/orders/{$pedido->id}")
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->getJson('/api/v1/customers')
        );

        $this->assertNegadoPorAutorizacao(
            $this->comoCreateRole()->postJson('/api/v1/customers', [
                'company_id' => $this->company->id,
                'name' => 'Coringa',
            ])
        );

        $this->assertSame(Order::STATUS_DRAFT, $pedido->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Isolamento entre estabelecimentos
    // ---------------------------------------------------------------

    public function test_pedido_de_outro_estabelecimento_nao_e_alcancavel(): void
    {
        $alheio = Order::factory()
            ->forCompany($this->outraCompany)
            ->status(Order::STATUS_DRAFT)
            ->create();

        $this->comoGerente()->getJson("/api/v1/orders/{$alheio->id}")->assertNotFound();
        $this->comoGerente()->putJson("/api/v1/orders/{$alheio->id}/status", [
            'status' => Order::STATUS_PENDING,
        ])->assertNotFound();
        $this->comoGerente()->deleteJson("/api/v1/orders/{$alheio->id}")->assertNotFound();

        $this->assertSame(Order::STATUS_DRAFT, $alheio->fresh()->status);
    }

    public function test_cliente_de_outro_estabelecimento_nao_e_alcancavel(): void
    {
        $alheio = Customer::factory()->forCompany($this->outraCompany)->create();

        $this->comoGerente()->getJson("/api/v1/customers/{$alheio->id}")->assertNotFound();

        $resposta = $this->comoLeitor()->getJson('/api/v1/customers')->assertOk();
        $this->assertSame([], $resposta->json('data'));
    }
}
