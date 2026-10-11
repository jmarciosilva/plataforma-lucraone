<?php

namespace App\Modules\Pdv\Domain\Exceptions;

use RuntimeException;

/**
 * Recusa prevista de um fluxo de operador.
 *
 * Carrega o código público e o status, e NÃO carrega o motivo real. O motivo
 * fica no ramo do serviço que decidiu, deliberadamente longe da resposta: se a
 * exceção transportasse "senha errada", "sem atribuição de filial" ou "vínculo
 * suspenso", o renderizador acabaria publicando essa distinção um dia — e cada
 * uma delas é um oráculo sobre quem tem conta, quem trabalha onde e quem foi
 * afastado.
 *
 * A mensagem é fixa e interna. Está em `dontReport`, como a do ciclo de
 * credencial: é decisão de domínio, não defeito.
 */
final class OperatorSessionFailed extends RuntimeException
{
    /** Credenciais, conta, vínculo, filial ou permissão: resposta única. */
    public const CODE_AUTHENTICATION = 'operator_authentication_failed';

    /** Outro operador já está com o caixa. */
    public const CODE_CONFLICT = 'operator_session_conflict';

    /** A sessão apresentada não vale mais, ou não é deste Terminal. */
    public const CODE_SESSION_INVALID = 'operator_session_invalid';

    /** O ator humano não pode administrar esta atribuição. */
    public const CODE_FORBIDDEN = 'forbidden';

    public function __construct(public readonly string $errorCode, public readonly int $httpStatus)
    {
        parent::__construct('PDV operator operation refused.');
    }

    public static function autenticacao(): self
    {
        return new self(self::CODE_AUTHENTICATION, 401);
    }

    public static function conflito(): self
    {
        return new self(self::CODE_CONFLICT, 409);
    }

    public static function sessaoInvalida(): self
    {
        return new self(self::CODE_SESSION_INVALID, 401);
    }

    public static function proibido(): self
    {
        return new self(self::CODE_FORBIDDEN, 403);
    }
}
