<?php

namespace App\Modules\Automation\Application\Actions;

/**
 * Troca `{campo}` pelo valor correspondente do payload do gatilho.
 *
 * Permite escrever "estoque de {produto} chegou a {quantidade}" no formulário.
 * Só substitui chaves presentes no payload; qualquer outra fica literal, para
 * o operador enxergar o erro em vez de receber um texto com buraco silencioso.
 */
class PlaceholderInterpolator
{
    public function aplicar(string $texto, array $payload): string
    {
        return preg_replace_callback(
            '/\{([a-z_]+)\}/i',
            function (array $encontrado) use ($payload) {
                $chave = $encontrado[1];

                if (! array_key_exists($chave, $payload)) {
                    return $encontrado[0];
                }

                $valor = $payload[$chave];

                if (is_bool($valor)) {
                    return $valor ? 'sim' : 'não';
                }

                if (is_float($valor) || (is_numeric($valor) && str_contains((string) $valor, '.'))) {
                    return number_format((float) $valor, 2, ',', '.');
                }

                return is_scalar($valor) ? (string) $valor : $encontrado[0];
            },
            $texto
        );
    }
}
