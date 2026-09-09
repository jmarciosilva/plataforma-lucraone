<?php

namespace App\Modules\Automation\Domain;

/**
 * Catálogo de gatilhos: o que existe e quais campos cada um expõe.
 *
 * Este arquivo é a fronteira de segurança do módulo. A condição da regra é
 * guardada como JSON, mas nunca é query livre: campo, tipo e operador só valem
 * se estiverem aqui. Um campo que não está no catálogo é recusado na validação
 * e ignorado na avaliação.
 */
class TriggerCatalog
{
    public const PRODUTO_CRIADO = 'product_created';

    public const ESTOQUE_BAIXO = 'stock_low';

    public const PEDIDO_CONCLUIDO = 'order_completed';

    public const TIPO_TEXTO = 'texto';

    public const TIPO_NUMERO = 'numero';

    public const TIPO_LISTA = 'lista';

    /**
     * @return array<string, array{rotulo: string, descricao: string, campos: array<string, array{rotulo: string, tipo: string}>}>
     */
    public static function todos(): array
    {
        return [
            self::PRODUTO_CRIADO => [
                'rotulo' => 'produto criado',
                'descricao' => 'dispara quando um produto novo é cadastrado.',
                'campos' => [
                    'nome' => ['rotulo' => 'nome do produto', 'tipo' => self::TIPO_TEXTO],
                    'sku' => ['rotulo' => 'sku', 'tipo' => self::TIPO_TEXTO],
                    'status' => ['rotulo' => 'status', 'tipo' => self::TIPO_TEXTO],
                    'company_id' => ['rotulo' => 'empresa', 'tipo' => self::TIPO_LISTA],
                ],
            ],

            self::ESTOQUE_BAIXO => [
                'rotulo' => 'estoque baixo',
                'descricao' => 'dispara quando o saldo de um produto cruza para baixo do ponto de reposição.',
                'campos' => [
                    'produto' => ['rotulo' => 'nome do produto', 'tipo' => self::TIPO_TEXTO],
                    'sku' => ['rotulo' => 'sku', 'tipo' => self::TIPO_TEXTO],
                    'quantidade' => ['rotulo' => 'quantidade em mãos', 'tipo' => self::TIPO_NUMERO],
                    'ponto_reposicao' => ['rotulo' => 'ponto de reposição', 'tipo' => self::TIPO_NUMERO],
                    'company_id' => ['rotulo' => 'empresa', 'tipo' => self::TIPO_LISTA],
                ],
            ],

            self::PEDIDO_CONCLUIDO => [
                'rotulo' => 'pedido concluído',
                'descricao' => 'dispara quando um pedido chega ao status concluído.',
                'campos' => [
                    'numero' => ['rotulo' => 'número do pedido', 'tipo' => self::TIPO_TEXTO],
                    'total' => ['rotulo' => 'valor total', 'tipo' => self::TIPO_NUMERO],
                    'itens' => ['rotulo' => 'quantidade de itens', 'tipo' => self::TIPO_NUMERO],
                    'cliente' => ['rotulo' => 'nome do cliente', 'tipo' => self::TIPO_TEXTO],
                    'company_id' => ['rotulo' => 'empresa', 'tipo' => self::TIPO_LISTA],
                ],
            ],
        ];
    }

    public static function chaves(): array
    {
        return array_keys(self::todos());
    }

    public static function existe(string $gatilho): bool
    {
        return array_key_exists($gatilho, self::todos());
    }

    public static function rotulo(string $gatilho): string
    {
        return self::todos()[$gatilho]['rotulo'] ?? $gatilho;
    }

    /**
     * Campos aceitos por um gatilho. Vazio se o gatilho não existir.
     */
    public static function campos(string $gatilho): array
    {
        return self::todos()[$gatilho]['campos'] ?? [];
    }

    public static function campoExiste(string $gatilho, string $campo): bool
    {
        return array_key_exists($campo, self::campos($gatilho));
    }

    public static function tipoDoCampo(string $gatilho, string $campo): ?string
    {
        return self::campos($gatilho)[$campo]['tipo'] ?? null;
    }

    /**
     * Rótulos para montar os selects da tela.
     */
    public static function opcoesDeGatilho(): array
    {
        return collect(self::todos())
            ->map(fn (array $gatilho) => $gatilho['rotulo'])
            ->all();
    }

    public static function opcoesDeCampo(string $gatilho): array
    {
        return collect(self::campos($gatilho))
            ->map(fn (array $campo) => $campo['rotulo'])
            ->all();
    }

    /**
     * Gatilhos que carregam um produto no payload.
     *
     * A ação de atualizar preço só faz sentido nesses — sem produto não há o
     * que reprecificar.
     */
    public static function comProduto(): array
    {
        return [self::PRODUTO_CRIADO, self::ESTOQUE_BAIXO];
    }
}
