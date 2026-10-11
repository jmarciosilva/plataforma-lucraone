<?php

namespace App\Modules\Identity\Infrastructure\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Provider de autenticação da identidade do LucraOne.
 *
 * Existe por uma razão específica e verificada: desde que `users.email` passou a
 * aceitar NULL — para que um operador de caixa exista sem e-mail —, o provider
 * padrão do Laravel ficou com um buraco.
 *
 * `EloquentUserProvider::retrieveByCredentials()` monta a consulta com
 * `$query->where($chave, $valor)` para cada credencial. No query builder,
 * `where('email', null)` é traduzido para `email IS NULL`. Então uma tentativa
 * com `email` nulo — `{"email": null, "password": "..."}` — deixaria de ser uma
 * busca sem resultado e passaria a casar com QUALQUER pessoa sem e-mail,
 * exatamente o conjunto dos operadores de caixa. Bastaria acertar a senha de um
 * deles. Isto foi reproduzido em teste antes de existir esta classe.
 *
 * Os FormRequests de login (web e API) exigem e-mail e já barravam esse corpo. A
 * correção aqui é de profundidade: a garantia não deve morar numa regra de
 * validação de tela, porque qualquer novo caminho de autenticação — um comando,
 * um job, um endpoint futuro — herdaria o buraco sem perceber.
 *
 * A regra é mínima e genérica: credencial de identificação nula ou vazia nunca
 * identifica ninguém. Vale para `email` e para qualquer outro identificador que
 * venha a ser usado, sem precisar enumerar colunas.
 */
class IdentityUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     * @return (Authenticatable&Model)|null
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        foreach ($credentials as $chave => $valor) {
            if (str_contains($chave, 'password')) {
                continue;
            }

            // Null e string vazia param aqui. Ausência de identificador é
            // ausência de pessoa, nunca "qualquer pessoa cujo campo é nulo".
            if ($valor === null || $valor === '') {
                return null;
            }
        }

        return parent::retrieveByCredentials($credentials);
    }
}
