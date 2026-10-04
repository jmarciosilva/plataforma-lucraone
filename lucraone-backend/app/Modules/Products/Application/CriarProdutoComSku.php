<?php

namespace App\Modules\Products\Application;

use App\Modules\Products\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CriarProdutoComSku
{
    public function criar(array $dados, TenantContext $context): Product
    {
        $tenantId = $context->tenant()->id;
        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            try {
                return DB::transaction(function () use ($dados, $tenantId) {
                    // Serializa geradores Web do mesmo estabelecimento. A constraint
                    // continua protegendo contra inserções concorrentes pela API.
                    Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();

                    return Product::create([
                        ...$dados,
                        'tenant_id' => $tenantId,
                        'sku' => $this->proximo($dados['name'], $tenantId),
                    ]);
                }, 3);
            } catch (UniqueConstraintViolationException $exception) {
                $mensagem = strtolower($exception->getMessage());
                if (! str_contains($mensagem, 'products_tenant_id_sku_unique')
                    && ! str_contains($mensagem, 'products.tenant_id, products.sku')) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages(['sku' => 'Não foi possível gerar um código livre. Tente novamente.']);
    }

    public function proximo(string $nome, string $tenantId): string
    {
        $base = trim(preg_replace('/[^A-Z0-9]+/', '-', strtoupper(Str::ascii($nome))), '-');
        $base = rtrim(substr($base, 0, 100), '-') ?: 'PRODUTO';
        $codigo = $base;
        for ($numero = 2; Product::withTrashed()->where('tenant_id', $tenantId)->where('sku', $codigo)->exists(); $numero++) {
            $sufixo = '-'.$numero;
            $codigo = rtrim(substr($base, 0, 100 - strlen($sufixo)), '-').$sufixo;
        }

        return $codigo;
    }
}
