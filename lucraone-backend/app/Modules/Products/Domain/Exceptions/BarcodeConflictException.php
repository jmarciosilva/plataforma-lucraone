<?php

namespace App\Modules\Products\Domain\Exceptions;

use RuntimeException;

/**
 * O mesmo código de barras está num produto e numa embalagem do mesmo
 * estabelecimento.
 *
 * A validação (BarcodeAvailable) impede esse estado, mas nenhum índice do banco
 * cobre as duas tabelas. Se ele aparecer, a leitura falha em vez de escolher um
 * dos dois em silêncio.
 */
class BarcodeConflictException extends RuntimeException
{
    public static function for(string $barcode): self
    {
        return new self("código de barras {$barcode} está em um produto e em uma embalagem ao mesmo tempo.");
    }
}
