<?php

namespace App\Modules\Pdv\Http\Controllers;

use App\Modules\Pdv\Application\EligibleOperators;
use App\Modules\Terminals\Application\TerminalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/pdv/operators — quem pode operar este caixa.
 *
 * Existe por ergonomia de loja: o PDV mostra os nomes e o operador toca no
 * dele em vez de digitar. A digitação manual continua funcionando, e a senha
 * continua obrigatória — **este endpoint não autentica ninguém.**
 *
 * A filial sai do `TerminalContext`, que o middleware de máquina preencheu a
 * partir do Terminal autenticado. Não há parâmetro de filial, de empresa nem de
 * estabelecimento, pelo mesmo motivo do `GET /terminal`: não existe "qual
 * filial?" a responder, então não existe escolha a validar nem a esquecer de
 * validar.
 *
 * O que sai é o mínimo de uma tela de seleção: identificador não secreto, nome
 * de exibição e username. Nada de e-mail, senha, hash, credencial, token, e
 * nada de outro estabelecimento ou de outra filial.
 */
final class EligibleOperatorsController
{
    /** Teto de página. Um supermercado grande tem dezenas, não milhares. */
    private const MAX_POR_PAGINA = 100;

    private const PADRAO_POR_PAGINA = 50;

    public function __construct(
        private EligibleOperators $elegiveis,
        private TerminalContext $contexto,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $terminal = $this->contexto->terminal();

        $pagina = max(1, (int) $request->query('page', 1));
        $porPagina = (int) $request->query('per_page', self::PADRAO_POR_PAGINA);
        $porPagina = max(1, min($porPagina, self::MAX_POR_PAGINA));

        $resultado = $this->elegiveis->paraTerminal($terminal, $porPagina, $pagina);

        // Filial sem operador ativo é estado NORMAL, não erro: uma loja recém
        // cadastrada começa assim. Devolve lista vazia e 200, para o cliente
        // mostrar "nenhum operador liberado neste caixa" em vez de uma falha.
        return response()->json([
            'data' => [
                'branch_id' => $terminal->branch_id,
                'operators' => array_map(
                    fn (object $linha) => [
                        'id' => (string) $linha->id,
                        'display_name' => (string) $linha->display_name,
                        'username' => (string) $linha->username,
                    ],
                    $resultado->items(),
                ),
            ],
            'meta' => [
                'page' => $resultado->currentPage(),
                'per_page' => $resultado->perPage(),
                'total' => $resultado->total(),
                'last_page' => $resultado->lastPage(),
            ],
        ])
            // Lista de funcionários de uma loja: não é segredo, mas também não
            // é para ficar em cache de proxy.
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
