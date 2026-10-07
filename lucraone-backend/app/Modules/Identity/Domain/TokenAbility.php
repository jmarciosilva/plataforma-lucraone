<?php

namespace App\Modules\Identity\Domain;

/**
 * Abilities dos tokens de API.
 *
 * Fonte única para não espalhar literais entre quem emite o token
 * (`AuthController`) e quem o confere (`EnsureTokenAbility`).
 *
 * O conjunto é deliberadamente pequeno. Ability não é permissão: quem decide
 * se *esta pessoa* pode mexer em produto são as Policies do SEC-01, pelo papel
 * dela no estabelecimento. A ability diz o que *este token* pode fazer,
 * independentemente de quem o carrega — são perguntas diferentes, e duplicar o
 * RBAC em abilities Sanctum só criaria duas verdades para a mesma regra.
 *
 * Por isso a divisão é por natureza da operação, leitura ou escrita, e não por
 * módulo: é o eixo em que um token pode ser legitimamente mais restrito que o
 * papel de quem o pediu.
 *
 * Abilities de terminal/PDV não pertencem aqui: serão definidas junto da
 * entidade Terminal, quando ela existir.
 */
final class TokenAbility
{
    /** Ler dados de negócio pela API. */
    public const LEITURA = 'business:read';

    /** Criar, alterar e remover dados de negócio pela API. */
    public const ESCRITA = 'business:write';

    /**
     * Abilities de um token de sessão humana emitido pelo login da API.
     *
     * Hoje o login é o único emissor e atende um cliente que lê e escreve, logo
     * recebe as duas. O que isto elimina não é o alcance atual, é o coringa:
     * com `['*']` o mesmo token passaria a valer automaticamente para qualquer
     * ability criada depois — provisionar terminal, por exemplo — sem ninguém
     * decidir isso.
     *
     * @return list<string>
     */
    public static function paraSessaoHumana(): array
    {
        return [self::LEITURA, self::ESCRITA];
    }
}
