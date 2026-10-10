<?php

namespace App\Modules\Pdv\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * GET /api/v1/pdv/health — conectividade do contrato PDV.
 *
 * Público e deliberadamente mínimo. Responde uma única pergunta, que é a que o
 * aplicativo de loja precisa fazer: "estou falando com o backend certo, na
 * versão certa do contrato?".
 *
 * NÃO é alias de `/api/health`. Aquele existe para quem opera a plataforma e
 * expõe estado de banco e cache; este é consumido por um aplicativo instalado
 * em loja, fora do nosso perímetro, e tudo que ele devolve é efetivamente
 * público. Ambiente, versão de PHP/Laravel, host, caminho, commit, estado de
 * dependências — nada disso tem consumidor aqui e cada um ajudaria quem está
 * mapeando a infraestrutura.
 *
 * Também não devolve timestamp: relógio de servidor não é contrato, e o PDV não
 * deve sincronizar hora por este endpoint.
 */
class PdvHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'api' => 'pdv',
                'version' => 'v1',
            ],
        ]);
    }
}
