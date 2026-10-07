<?php

namespace Tests\Concerns;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;

/**
 * Dá a um usuário de teste as permissões de negócio do estabelecimento.
 *
 * Antes do SEC-01 a API não verificava permissão, então os testes de API
 * autenticavam um usuário sem papel nenhum e isso bastava. Com as Policies
 * aplicadas, esse usuário passa a receber 403 — corretamente. Estes testes
 * exercitam o comportamento funcional dos endpoints, não a autorização, então
 * o que eles precisam é de um usuário legitimamente autorizado.
 *
 * Usa a matriz real do estabelecimento (StandardRoleMatrix), não permissões
 * avulsas: assim o fixture não pode divergir do modelo de autorização de
 * produção. Quem cobre a recusa são os ApiAuthorization*Test.
 */
trait AutorizaUsuarioDeApi
{
    protected function autorizarNaApi(Tenant $tenant, User $usuario): User
    {
        $provisionador = app(ProvisionarEstabelecimento::class);
        $provisionador->provisionarMatriz($tenant);
        $provisionador->atribuirAdministrador($tenant, $usuario);

        return $usuario->refresh();
    }
}
