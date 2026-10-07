<?php

namespace Tests\Feature\Domain;

use App\Modules\Companies\Domain\Models\Company;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Products\Domain\Models\Category;
use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * COR-01 — soft delete e unicidade.
 *
 * Política do domínio: **arquivar não libera o identificador**. O índice único
 * do banco cobre as linhas arquivadas, e é isso que se quer — SKU, slug e
 * e-mail continuam pertencendo ao registro histórico, e voltar a usá-los é
 * restaurar o registro, não criar outro.
 *
 * O problema do item nunca foi o índice; era a aplicação não dizer a mesma
 * coisa que o banco. No painel a mensagem era "já existe" para algo invisível
 * nas listagens, e na API de produtos e categorias não havia validação alguma:
 * a violação de constraint subia como erro de banco.
 */
class SoftDeleteUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $outroTenant;

    private Company $company;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $provisionador = app(ProvisionarEstabelecimento::class);

        $this->tenant = Tenant::factory()->active()->create(['slug' => 'casa-alta']);
        $this->outroTenant = Tenant::factory()->active()->create(['slug' => 'casa-nova']);
        $provisionador->provisionarMatriz($this->tenant);
        $provisionador->provisionarMatriz($this->outroTenant);

        $this->company = Company::factory()->forCurrentTenant($this->tenant->id)->active()->create();

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $provisionador->atribuirAdministrador($this->tenant, $this->admin);
        $this->admin->refresh();

        $this->token = $this->admin->createToken('cor01')->plainTextToken;
    }

    // ---------------------------------------------------------------
    // Auxiliares
    // ---------------------------------------------------------------

    private function api()
    {
        return $this->withToken($this->token)->withHeader('X-Tenant-ID', $this->tenant->id);
    }

    private function painel()
    {
        return $this->actingAs($this->admin)->withSession(['tenant_ativo' => $this->tenant->id]);
    }

    private function produto(string $sku, ?Tenant $tenant = null): Product
    {
        return Product::factory()->create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'company_id' => $tenant ? Company::factory()->forCurrentTenant($tenant->id)->create()->id : $this->company->id,
            'sku' => $sku,
            'status' => 'active',
        ]);
    }

    private function categoria(string $slug, ?Tenant $tenant = null): Category
    {
        return Category::factory()->create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'slug' => $slug,
            'parent_id' => null,
        ]);
    }

    private function payloadProduto(string $sku): array
    {
        return [
            'company_id' => $this->company->id,
            'sku' => $sku,
            'name' => 'Produto',
            'status' => 'active',
        ];
    }

    // ---------------------------------------------------------------
    // Product — API (onde não havia validação nenhuma)
    // ---------------------------------------------------------------

    public function test_api_recusa_sku_de_produto_ativo(): void
    {
        $this->produto('SKU-001');

        $this->api()->postJson('/api/v1/products', $this->payloadProduto('SKU-001'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);

        $this->assertSame(1, Product::withoutGlobalScopes()->where('sku', 'SKU-001')->count());
    }

    public function test_api_recusa_sku_de_produto_arquivado_e_explica(): void
    {
        $this->produto('SKU-002')->delete();

        $resposta = $this->api()->postJson('/api/v1/products', $this->payloadProduto('SKU-002'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);

        $this->assertStringContainsString(
            'arquivado',
            $resposta->json('errors.sku.0'),
            'a mensagem precisa dizer que o registro está arquivado, senão o operador procura por algo que não aparece'
        );
    }

    public function test_api_distingue_ativo_de_arquivado_na_mensagem(): void
    {
        $this->produto('SKU-ATIVO');
        $this->produto('SKU-ARQUIVADO')->delete();

        $ativo = $this->api()->postJson('/api/v1/products', $this->payloadProduto('SKU-ATIVO'))->json('errors.sku.0');
        $arquivado = $this->api()->postJson('/api/v1/products', $this->payloadProduto('SKU-ARQUIVADO'))->json('errors.sku.0');

        $this->assertNotSame($ativo, $arquivado);
        $this->assertStringContainsString('ativo', $ativo);
        $this->assertStringContainsString('arquivado', $arquivado);
    }

    public function test_api_recusa_update_para_sku_de_outro_produto(): void
    {
        $this->produto('SKU-010');
        $alvo = $this->produto('SKU-011');

        $this->api()->putJson("/api/v1/products/{$alvo->id}", ['sku' => 'SKU-010'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);

        $this->assertSame('SKU-011', $alvo->fresh()->sku);
    }

    public function test_api_recusa_update_para_sku_arquivado(): void
    {
        $this->produto('SKU-020')->delete();
        $alvo = $this->produto('SKU-021');

        $this->api()->putJson("/api/v1/products/{$alvo->id}", ['sku' => 'SKU-020'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_api_aceita_update_mantendo_o_proprio_sku(): void
    {
        $alvo = $this->produto('SKU-030');

        $this->api()->putJson("/api/v1/products/{$alvo->id}", ['sku' => 'SKU-030', 'name' => 'Outro nome'])
            ->assertOk();
    }

    // ---------------------------------------------------------------
    // Category — API
    // ---------------------------------------------------------------

    public function test_api_recusa_slug_de_categoria_ativa(): void
    {
        $this->categoria('bebidas');

        $this->api()->postJson('/api/v1/categories', ['name' => 'Bebidas', 'slug' => 'bebidas'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        $this->assertSame(1, Category::withoutGlobalScopes()->where('slug', 'bebidas')->count());
    }

    public function test_api_recusa_slug_de_categoria_arquivada_e_explica(): void
    {
        $this->categoria('laticinios')->delete();

        $resposta = $this->api()->postJson('/api/v1/categories', ['name' => 'Laticínios', 'slug' => 'laticinios'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        $this->assertStringContainsString('arquivad', $resposta->json('errors.slug.0'));
    }

    // ---------------------------------------------------------------
    // Product e Category — painel web
    // ---------------------------------------------------------------

    public function test_painel_explica_sku_arquivado(): void
    {
        $this->produto('SKU-WEB-1')->delete();

        $this->painel()
            ->post(route('catalog.products.store'), [
                'sku' => 'SKU-WEB-1',
                'name' => 'Produto',
                'unit' => 'UN',
                'company_id' => $this->company->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('sku');

        $erro = session('errors')->first('sku');
        $this->assertStringContainsString('arquivado', $erro);
    }

    public function test_painel_explica_slug_de_categoria_arquivada(): void
    {
        $this->categoria('congelados')->delete();

        $this->painel()
            ->post(route('catalog.categories.store'), ['name' => 'Congelados', 'slug' => 'congelados'])
            ->assertSessionHasErrors('slug');

        $this->assertStringContainsString('arquivad', session('errors')->first('slug'));
    }

    // ---------------------------------------------------------------
    // Tenant — slug global
    // ---------------------------------------------------------------

    public function test_slug_de_tenant_arquivado_permanece_reservado(): void
    {
        $arquivado = Tenant::factory()->active()->create(['slug' => 'padaria-central']);
        $arquivado->delete();

        $plataforma = User::factory()->platformAdmin()->forTenant($this->tenant)->create();
        app(ProvisionarEstabelecimento::class)->atribuirAdministrador($this->tenant, $plataforma);

        $this->actingAs($plataforma->refresh())
            ->withSession(['tenant_ativo' => $this->tenant->id])
            ->post(route('tenants.store'), [
                'name' => 'Padaria Nova',
                'slug' => 'padaria-central',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(
            1,
            Tenant::withTrashed()->where('slug', 'padaria-central')->count(),
            'o slug continua pertencendo ao estabelecimento histórico'
        );
    }

    public function test_tenant_arquivado_pode_ser_restaurado(): void
    {
        $arquivado = Tenant::factory()->active()->create(['slug' => 'mercado-norte']);
        $arquivado->delete();

        $this->assertTrue($arquivado->fresh()->trashed());

        $arquivado->restore();

        $this->assertFalse($arquivado->fresh()->trashed());
        $this->assertSame('mercado-norte', $arquivado->fresh()->slug);
    }

    // ---------------------------------------------------------------
    // User — identidade global
    // ---------------------------------------------------------------

    public function test_email_ativo_reutiliza_a_mesma_identidade_global(): void
    {
        $pessoa = User::factory()->forTenant($this->outroTenant)->create(['email' => 'contador@exemplo.test']);

        $this->painel()->post(route('users.store'), [
            'name' => 'Contador',
            'email' => 'contador@exemplo.test',
            'password' => 'segredo-de-teste',
            'password_confirmation' => 'segredo-de-teste',
            'status' => TenantUser::STATUS_ACTIVE,
        ])->assertSessionHasNoErrors();

        // Uma identidade, dois vínculos — nunca duas contas com o mesmo e-mail.
        $this->assertSame(1, User::withTrashed()->where('email', 'contador@exemplo.test')->count());
        $this->assertTrue($pessoa->fresh()->canAccessTenant($this->tenant->id));
    }

    public function test_email_arquivado_e_recusado_sem_restaurar_em_silencio(): void
    {
        $arquivado = User::factory()->forTenant($this->outroTenant)->create(['email' => 'antigo@exemplo.test']);
        $arquivado->delete();

        $this->painel()->post(route('users.store'), [
            'name' => 'Antigo',
            'email' => 'antigo@exemplo.test',
            'password' => 'segredo-de-teste',
            'password_confirmation' => 'segredo-de-teste',
            'status' => TenantUser::STATUS_ACTIVE,
        ])->assertSessionHasErrors('email');

        $this->assertTrue($arquivado->fresh()->trashed(), 'criar vínculo não pode restaurar identidade global');
        $this->assertSame(1, User::withTrashed()->where('email', 'antigo@exemplo.test')->count());
    }

    public function test_usuario_arquivado_pode_ser_restaurado_preservando_o_id(): void
    {
        $pessoa = User::factory()->forTenant($this->tenant)->create(['email' => 'volta@exemplo.test']);
        $idOriginal = $pessoa->id;
        $pessoa->delete();

        $pessoa->restore();

        $this->assertFalse($pessoa->fresh()->trashed());
        $this->assertSame($idOriginal, $pessoa->fresh()->id, 'restaurar preserva a identidade, não cria outra');
    }

    // ---------------------------------------------------------------
    // Restore não colide, por construção
    // ---------------------------------------------------------------

    public function test_restore_de_produto_nao_colide_porque_o_sku_nunca_foi_liberado(): void
    {
        $produto = $this->produto('SKU-RESTORE');
        $produto->delete();

        // Enquanto está arquivado, ninguém conseguiu tomar o SKU:
        $this->api()->postJson('/api/v1/products', $this->payloadProduto('SKU-RESTORE'))->assertStatus(422);

        $produto->restore();

        $this->assertFalse($produto->fresh()->trashed());
        $this->assertSame('SKU-RESTORE', $produto->fresh()->sku);
    }

    public function test_restore_de_categoria_nao_colide(): void
    {
        $categoria = $this->categoria('padaria');
        $categoria->delete();

        $this->api()->postJson('/api/v1/categories', ['name' => 'Padaria', 'slug' => 'padaria'])->assertStatus(422);

        $categoria->restore();

        $this->assertFalse($categoria->fresh()->trashed());
        $this->assertSame('padaria', $categoria->fresh()->slug);
    }

    // ---------------------------------------------------------------
    // Isolamento por estabelecimento preservado
    // ---------------------------------------------------------------

    public function test_mesmo_sku_em_estabelecimentos_diferentes_continua_permitido(): void
    {
        $this->produto('SKU-COMPARTILHADO', $this->outroTenant);

        $this->api()->postJson('/api/v1/products', $this->payloadProduto('SKU-COMPARTILHADO'))
            ->assertCreated();

        $this->assertSame(2, Product::withoutGlobalScopes()->where('sku', 'SKU-COMPARTILHADO')->count());
    }

    public function test_mesmo_sku_arquivado_em_outro_estabelecimento_nao_bloqueia(): void
    {
        $this->produto('SKU-ALHEIO', $this->outroTenant)->delete();

        $this->api()->postJson('/api/v1/products', $this->payloadProduto('SKU-ALHEIO'))
            ->assertCreated();
    }

    public function test_mesmo_slug_de_categoria_em_estabelecimentos_diferentes_continua_permitido(): void
    {
        $this->categoria('mercearia', $this->outroTenant);

        $this->api()->postJson('/api/v1/categories', ['name' => 'Mercearia', 'slug' => 'mercearia'])
            ->assertCreated();
    }

    // ---------------------------------------------------------------
    // Banco e aplicação dizem a mesma coisa
    // ---------------------------------------------------------------

    public function test_banco_recusa_sku_duplicado_mesmo_arquivado(): void
    {
        $produto = $this->produto('SKU-BANCO');
        $produto->delete();

        $this->expectException(QueryException::class);

        DB::table('products')->insert([
            'id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'sku' => 'SKU-BANCO',
            'name' => 'Colidente',
            'unit' => 'UN',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_banco_recusa_slug_de_tenant_duplicado_mesmo_arquivado(): void
    {
        $arquivado = Tenant::factory()->active()->create(['slug' => 'banco-tenant']);
        $arquivado->delete();

        $this->expectException(QueryException::class);

        DB::table('tenants')->insert([
            'id' => '01HZZZZZZZZZZZZZZZZZZZZZZY',
            'name' => 'Colidente',
            'slug' => 'banco-tenant',
            'status' => 'ACTIVE',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_banco_recusa_email_duplicado_mesmo_arquivado(): void
    {
        $pessoa = User::factory()->forTenant($this->tenant)->create(['email' => 'banco@exemplo.test']);
        $pessoa->delete();

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'id' => '01HZZZZZZZZZZZZZZZZZZZZZZX',
            'name' => 'Colidente',
            'email' => 'banco@exemplo.test',
            'password' => 'irrelevante',
            'status' => User::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
