<?php

namespace App\Modules\Pdv\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Envelope público de erro dos contratos `/api/v1/pdv/*`.
 *
 * Fonte única: middlewares de máquina e renderizadores de exceção passam por
 * aqui. Duas definições do mesmo envelope divergiriam no primeiro ajuste, e o
 * PDV é um cliente distribuído em loja — mudar a forma do erro depois de a
 * integração existir é caro.
 *
 * O formato é deliberadamente pobre em informação:
 *
 *     {"error": {"code": "...", "message": "...", "request_id": "..."}}
 *
 * `code` é estável e destinado a código; `message` é texto para operador, em
 * português, sem detalhe interno; `request_id` é o que o suporte usa para
 * cruzar com o log do servidor — é por isso que ele vai no corpo além do
 * cabeçalho, já que quem atende a loja costuma ter a tela, não o header.
 *
 * O que NUNCA entra: motivo interno de pairing, nome de classe, SQL, caminho de
 * arquivo, stack trace, segredo, ou qualquer distinção que permita a um
 * atacante inferir estado do servidor.
 */
final class PdvErrorResponse
{
    public const CODE_VALIDATION = 'validation_error';

    public const CODE_PAIRING_FAILED = 'pairing_failed';

    public const CODE_UNAUTHENTICATED = 'unauthenticated';

    public const CODE_FORBIDDEN = 'forbidden';

    public const CODE_RATE_LIMITED = 'rate_limited';

    public const CODE_INTERNAL = 'internal_error';

    /**
     * @param  array<string, list<string>>  $errors  apenas para validação estrutural
     * @param  array<string, string>  $headers
     */
    public static function make(
        Request $request,
        string $code,
        string $message,
        int $status,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $erro = [
            'code' => $code,
            'message' => $message,
            'request_id' => self::requestId($request),
        ];

        if ($errors !== []) {
            $erro['errors'] = $errors;
        }

        return response()->json(['error' => $erro], $status, $headers);
    }

    /**
     * O mesmo identificador que o RequestCorrelationMiddleware pôs no
     * cabeçalho da resposta.
     *
     * Ele normaliza o valor no próprio objeto Request antes de a requisição
     * seguir, então ler o cabeçalho aqui devolve exatamente o que o cliente vai
     * ver em `X-Request-ID` — e não um segundo identificador que não casaria
     * com nada. O ULID de reserva cobre só o caso de uma falha anterior ao
     * middleware, em que não há cabeçalho algum.
     */
    private static function requestId(Request $request): string
    {
        $id = $request->headers->get('X-Request-ID');

        return is_string($id) && $id !== '' ? $id : (string) Str::ulid();
    }
}
