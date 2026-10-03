<?php

namespace Database\Seeders;

use App\Modules\Tenancy\Application\ProvisionarEstabelecimento;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Provisiona a autorização padrão dos estabelecimentos que já existem.
 *
 * Desde o ONB-01A a matriz vive em ProvisionarEstabelecimento, a mesma fonte
 * usada pela criação de estabelecimento no painel. Antes disso o seeder tinha
 * a sua própria lista e as duas respostas divergiam: 4 papéis e 28 permissões
 * aqui, 1 papel e 26 no painel.
 *
 * O seeder só provisiona a matriz. Atribuir administrador exige um usuário, e
 * aqui não há um: num seed de vários estabelecimentos não existe "quem criou".
 */
class AuthorizationSeeder extends Seeder
{
    public function __construct(
        private ProvisionarEstabelecimento $provisionamento
    ) {}

    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            $this->provisionamento->provisionarMatriz($tenant);
        }
    }
}
