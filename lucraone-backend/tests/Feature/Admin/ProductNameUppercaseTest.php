<?php

namespace Tests\Feature\Admin;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductNameUppercaseTest extends TestCase
{
    use RefreshDatabase;

    public static function fluxos(): array
    {
        return [
            ['web', false, 'Leite UHT Integral Italac 1L', 'LEITE UHT INTEGRAL ITALAC 1L'],
            ['web', true, 'Macarrão Espaguete', 'MACARRÃO ESPAGUETE'],
            ['api', false, 'Óleo de Soja', 'ÓLEO DE SOJA'],
            ['api', true, 'pão de açúcar', 'PÃO DE AÇÚCAR'],
            ['web', false, 'CAFÉ', 'CAFÉ'],
        ];
    }

    #[DataProvider('fluxos')]
    public function test_nome_persistido_e_normalizado_sem_alterar_outros_campos(string $fluxo, bool $editar, string $entrada, string $esperado): void
    {
        $tenant = Tenant::factory()->active()->create();
        $company = Company::factory()->forCurrentTenant($tenant->id)->create();
        $admin = User::factory()->forTenant($tenant)->create();
        $p = app(ProvisionarEstabelecimento::class);
        $p->provisionarMatriz($tenant);
        $p->atribuirAdministrador($tenant, $admin);
        $this->actingAs($admin);
        $dados = ['company_id' => $company->id, 'name' => $entrada, 'sku' => 'MANUAL-01', 'status' => 'active', 'unit' => 'UN', 'description' => 'Descrição preservada'];
        $produto = $editar ? Product::factory()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'sku' => 'MANUAL-01']) : null;
        if ($fluxo === 'api') {
            $this->withToken($admin->createToken('teste')->plainTextToken)->withHeader('X-Tenant-ID', $tenant->id);
            $resposta = $editar ? $this->putJson('/api/v1/products/'.$produto->id, $dados) : $this->postJson('/api/v1/products', $dados);
            $resposta->assertSuccessful()->assertJsonPath('data.name', $esperado);
        } else {
            $resposta = $editar ? $this->put(route('catalog.products.update', $produto), $dados) : $this->post(route('catalog.products.store'), $dados);
            $resposta->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseHas('products', ['tenant_id' => $tenant->id, 'name' => $esperado, 'sku' => 'MANUAL-01', 'description' => 'Descrição preservada']);
    }
}
