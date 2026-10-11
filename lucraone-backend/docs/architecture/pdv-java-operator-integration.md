# Contrato de integração — autenticação e sessão do operador (LucraOne PDV Java)

Documento de implementação para quem escreve o cliente Java. Tudo abaixo está
implementado em `feat/pdv-operator-auth-session` e validado por teste; nada foi
implantado.

Decisões e justificativas: [pdv-operator-auth-session.md](pdv-operator-auth-session.md).
Ciclo da credencial de máquina (pré-requisito): [pdv-machine-credential-lifecycle.md](pdv-machine-credential-lifecycle.md).

## 0. Pré-requisito obrigatório

As quatro rotas de operador exigem a ability de máquina `pdv:operator-session`.

| Credencial de máquina | Abilities | Autentica operador? |
|---|---|---|
| Emitida pelo pairing a partir desta versão | `pdv:terminal:read`, `pdv:credential:renew`, `pdv:credential:confirm`, `pdv:operator-session` | sim |
| Promovida por rotação a partir desta versão | as mesmas quatro | sim |
| LEGADA, em campo hoje | `pdv:terminal:read` | **não** — 403 `forbidden` |
| Candidata PENDENTE de rotação | `pdv:credential:confirm` | **não** — 403 `forbidden` |
| PAT humano | `business:read`, `business:write` | **não** — 403 `forbidden` |

**Um terminal já instalado precisa rotacionar a credencial uma vez antes de usar
este contrato.** Não há update em massa de PAT; a rotação segura é o caminho.
Sequência: `POST /terminal/credential/rotations` → persistir a candidata →
`POST /terminal/credential/confirm`. O detalhe está no documento do ciclo de
credencial.

Confira a ability antes de tentar: um 403 na primeira chamada de operador quase
sempre significa credencial legada, não configuração errada.

## 1. Autenticação conjunta

```http
Authorization: Bearer <MACHINE_CREDENTIAL>
X-PDV-Operator-Session: <OPERATOR_SESSION_CREDENTIAL>
```

A ordem de verificação no servidor:

1. credencial de máquina válida (Sanctum);
2. sujeito do tipo Terminal;
3. ability `pdv:operator-session`;
4. tenant/empresa/filial derivados do Terminal;
5. sessão do operador válida;
6. sessão pertence a ESTE terminal, instalação e PAT de máquina;
7. identidade, prazo e revogação da sessão;
8. conta, vínculo, filial e permissões revalidados AGORA;
9. operação autorizada.

Regras do cliente:

- `X-PDV-Operator-Session` **somente em cabeçalho**. Query string e corpo são
  recusados com 403, não ignorados;
- **não** envie `X-Tenant-ID` nas rotas de máquina — 403 mesmo que o valor esteja
  correto;
- **não** envie `terminal_id`, `company_id`, `branch_id` ou `tenant_id` no corpo:
  campo desconhecido recusa a requisição inteira com 422;
- TLS obrigatório. Nunca registre `Authorization`, `X-PDV-Operator-Session`,
  corpo de login, segredo de entrega ou lease em log, APM ou tracing.

`X-Request-ID` é preservado/normalizado pelo servidor e repetido no corpo de
erro. **Não é chave de idempotência.**

## 2. `POST /api/v1/pdv/operator-sessions` — login e abertura do turno

Cabeçalhos: `Authorization` (máquina). **Sem** `X-PDV-Operator-Session`.

```json
{"username": "operadorteste", "password": "<SENHA_DO_OPERADOR>"}
```

Exatamente dois campos. `username` é o identificador operacional por
estabelecimento (até 64 caracteres); o servidor normaliza para minúsculas sem
acentos. `password` de 6 a 255 caracteres. **`email` não é aceito nesta rota**
— login por e-mail existe em `POST /api/v1/auth/login` (painel web/API humana),
não aqui; uma requisição com `email` em vez de `username` recebe 422.

**201** — sessão nova. **200** — recuperação da própria sessão do mesmo operador
(resposta perdida). O corpo é o mesmo nos dois; a credencial é nova nos dois.

```json
{
  "data": {
    "session": {
      "id": "01M4M6MCX1QM291Z26G0S3X0HC",
      "status": "ACTIVE",
      "authenticated_at": "2026-10-11T00:43:31+00:00",
      "last_revalidated_at": "2026-10-11T00:43:31+00:00",
      "expires_at": "2026-10-11T08:43:31+00:00"
    },
    "operator": {
      "id": "01M4M6MCV43Q7JZ7RR3ZN4A5G7",
      "name": "Nome do Operador",
      "email": "operador@loja.test"
    },
    "context": {
      "terminal": {"id": "01M4...", "name": "Caixa 519", "status": "ACTIVE",
                   "installation_id": "c5f60af5-02bd-4fdc-9fb4-0d2eb4cbbaef"},
      "tenant": {"id": "01M4...", "name": "Loja Exemplo"},
      "company": {"id": "01M4...", "trade_name": "Exemplo Comércio"},
      "branch": {"id": "01M4...", "name": "Filial Centro", "code": "BR-2669"}
    },
    "capabilities": ["pdv:operator:session"],
    "offline": {"enabled": false},
    "credential": {
      "token_type": "PdvOperatorSession",
      "access_token": "cd1e811f06c5644f5199fb7f53b1c86801bec9e21ed1a6bd01713f02727fd03c",
      "expires_at": "2026-10-11T08:43:31+00:00"
    }
  }
}
```

`credential.access_token`: 64 caracteres hexadecimais minúsculos, 32 bytes de
CSPRNG. **Entregue uma única vez.** O servidor guarda apenas o SHA-256 e não tem
como reentregá-lo — perder o valor significa novo login.

`session.id` é público e serve para suporte e correlação de log. **Não
autentica.** Nunca o envie no lugar da credencial.

Todas as respostas sensíveis trazem `Cache-Control: no-store, private` e
`Pragma: no-cache`.

### Prazos

`session.expires_at` é DESLIZANTE: avança em 240 minutos (configurável em
`pdv.operator_sessions.sliding_minutes`) a cada uso autenticado legítimo
(revalidação bem-sucedida). **Revalidar estende o prazo.** Sem uso autenticado
por 240 minutos consecutivos, a sessão expira naturalmente.

Teto absoluto de jornada: desligado no piloto (`max_minutes = 0`). Quando
configurado, nenhum deslizamento passa dele e o fim da jornada exige novo login.

Se a credencial de máquina vence em 20 minutos, o turno vence em 20 minutos,
independentemente da janela deslizante. Renove a credencial antes que ela vença —
a renovação preserva a sessão (seção 8).

### `capabilities`

Nesta fase, sempre e somente `["pdv:operator:session"]`: há um operador
autenticado neste caixa. **Não autoriza venda, desconto, pagamento,
cancelamento, sangria nem fechamento.** Não assuma capacidade comercial a partir
dela, e não trate a lista como fixa — leia o que vier.

## 3. `GET /api/v1/pdv/operator-session` — estado atual

Cabeçalhos: `Authorization` + `X-PDV-Operator-Session`.

**200** com o mesmo corpo do login, **sem o bloco `credential`**. Não reentrega a
credencial.

**Não move `last_revalidated_at`** e portanto não renova a graça offline. Para
isso use a revalidação.

## 4. `POST /api/v1/pdv/operator-session/revalidate` — prova de conectividade

Cabeçalhos: `Authorization` + `X-PDV-Operator-Session`. Corpo vazio.

**200** com o mesmo corpo do GET. Reconfere todas as condições, move
`last_revalidated_at` e emite um lease novo quando o offline está habilitado.
NÃO estende `session.expires_at`.

Chame periodicamente enquanto houver rede. Sugestão: a cada 10–15 minutos, e
sempre ao reconectar. Nunca mais de uma vez a cada 2 segundos — o freio é 30/min
por terminal.

## 5. `DELETE /api/v1/pdv/operator-session` — logout

Cabeçalhos: `Authorization` + `X-PDV-Operator-Session`. **204** sem corpo.

Encerra a sessão humana. **Não** despareia o terminal, **não** revoga a
credencial de máquina, **não** fecha caixa financeiro.

Idempotente para a mesma credencial legitimamente encerrada: repetir devolve 204.
Funciona também depois de a credencial de máquina ter sido rotacionada — fechar
turno é sempre a direção segura, e o cliente precisa poder limpar o estado local.

Credencial desconhecida, malformada ou de outro terminal: **401**. A idempotência
não é passe para sondar credenciais.

## 6. Erros

Envelope único:

```json
{"error": {"code": "operator_authentication_failed",
           "message": "Não foi possível autenticar o operador.",
           "request_id": "01M4M6MCZ11BD8X9C5VM8CDS0A"}}
```

`422` de estrutura acrescenta `error.errors`, com mensagens genéricas e **sem eco
do valor enviado**.

| Status | `code` | Significado | Ação do cliente |
|---|---|---|---|
| 200 | — | consulta, revalidação, recuperação de sessão | seguir |
| 201 | — | sessão nova | persistir a credencial antes de qualquer outra coisa |
| 204 | — | logout aplicado | limpar o estado local |
| 401 | `unauthenticated` | problema da MÁQUINA: token ausente, inválido, expirado, rotacionado; terminal bloqueado/revogado; estabelecimento, empresa ou filial fora de operação | renovar credencial de máquina; se falhar, recuperação administrativa |
| 401 | `operator_authentication_failed` | login recusado. Causa **não** é revelada: senha, conta inexistente, conta inativa, vínculo suspenso, sem `access-pdv`, sem atribuição à filial, filial de outro estabelecimento | pedir credenciais novamente; **não** deduzir a causa; depois de 2 falhas, orientar a procurar o administrador |
| 401 | `operator_session_invalid` | sessão ausente, malformada, desconhecida, de outro terminal/instalação/credencial de máquina, expirada ou revogada | descartar o estado local e exigir novo login |
| 403 | `forbidden` | credencial de máquina sem `pdv:operator-session`; `X-Tenant-ID` numa rota de máquina; credencial de sessão em query string ou corpo | corrigir o cliente ou rotacionar a credencial. **Não** repetir em loop |
| 409 | `operator_session_conflict` | outro operador tem sessão ativa neste caixa | exibir quem precisa fazer logout; oferecer "encerrar turno" ou pedir revogação administrativa. **Não** repetir automaticamente |
| 422 | `validation_error` | estrutura do corpo | corrigir o schema, sem ecoar segredo |
| 429 | `rate_limited` | freio | respeitar `Retry-After`; backoff com jitter |
| 500 | `internal_error` | erro inesperado; nenhum detalhe interno é publicado | backoff; levar o `request_id` ao suporte |

Nunca assuma que a sessão caiu por causa de um 500 ou de um timeout: só
`operator_session_invalid` (ou um 401 de máquina que persiste depois da
renovação) autoriza descartar o turno.

## 7. Rate limiting

| Rota | Limites |
|---|---|
| `POST /operator-sessions` | 10/min por terminal; 30/10 min por IP; 5/min por (identificador + terminal); 10/10 min por identificador agregado |
| `POST /operator-session/revalidate` e `DELETE /operator-session` | 30/min por terminal |
| rotas administrativas | 60/10 min por usuário humano |

O freio vale **também para a senha correta**: depois de estourado, o acerto
seguinte recebe 429. Não tente "uma última vez"; respeite `Retry-After`.

## 8. Revogação — quando a sessão para de valer

Verificado a CADA requisição, sem depender de evento:

- operador desativado;
- vínculo com o estabelecimento deixa de estar ativo;
- atribuição à filial revogada;
- `access-pdv` removida;
- terminal bloqueado ou revogado;
- credencial de máquina revogada por **recuperação administrativa**;
- sessão expirada (janela deslizante esgotada ou teto de jornada atingido);
- logout;
- revogação administrativa.

**Renovação normal da credencial de máquina NÃO derruba o turno.** Na mesma
transação que confirma a rotação, o `machine_token_id` da sessão é atualizado
para o novo PAT — o operador continua logado de forma transparente, e o contador
`machine_rotations` avança. A sessão pode sobreviver a várias rotações.

**Recuperação administrativa encerra o turno.** Recuperação pressupõe perda,
suspeita de comprometimento ou troca física do equipamento; herdar a sessão
anterior seria uma falha de segurança. Após a recuperação, `machine_token_id` da
sessão aponta para um PAT revogado e o próximo acesso recebe
`operator_session_invalid` — o operador precisa entrar de novo.

Reativar uma conta **não** ressuscita a sessão: de `REVOKED`, `ENDED` e `EXPIRED`
não se volta.

## 9. Resposta perdida e retry

| Momento da perda | O que fazer |
|---|---|
| Resposta do login perdida | repetir o MESMO login (username + senha). Devolve 200 com a MESMA sessão e uma credencial NOVA; o prazo deslizante reinicia. A credencial anterior deixa de valer |
| Timeout/rede/5xx no login | backoff com jitter e repetir o login. Nunca mais de uma sessão será criada |
| 409 no login | há outro operador. Não gere tentativas em loop |
| Resposta da revalidação perdida | repetir a revalidação. É idempotente em efeito |
| Resposta do logout perdida | repetir o DELETE. Devolve 204 |
| Credencial de sessão perdida localmente | novo login. Não há endpoint que a reentregue |

Persista a credencial de sessão em armazenamento seguro **antes** de considerar o
login concluído. Se a persistência falhar, faça logout e repita o login: uma
sessão aberta cuja credencial o cliente não guardou fica ocupando o caixa até o
prazo ou até uma revogação administrativa.

A recuperação exige senha correta. Um retry com senha errada recebe 401 e **não**
rotaciona a credencial da sessão existente.

## 10. Operação offline

```text
Login do operador: ONLINE, sempre.
Sessão já autenticada: temporariamente válida offline.
Novo operador offline: NÃO permitido.
Reinício da aplicação offline: NÃO restaurar autorização automaticamente.
Sessão expirada: exige autenticação online.
Revogação conhecida: bloquear imediatamente.
Prazo máximo: 120 min desde a última revalidação online bem-sucedida.
```

O bloco `offline` está SEMPRE presente na resposta. Quando o recurso está
desligado — por feature flag ou por ausência de material criptográfico — ele é
apenas `{"enabled": false}`, e o cliente **não deve operar offline**.

```json
{
  "enabled": true,
  "format_version": 1,
  "algorithm": "Ed25519",
  "key_id": "prod-2026-1",
  "public_key": "o6dfe5teFDVU6R+1OL0KFrbBmRfg+L92E7u/bKIPb9I=",
  "grace_minutes": 120,
  "lease": "pdv-offline-lease-v1.prod-2026-1.<PAYLOAD_B64URL>.<SIG_B64URL>",
  "expires_at": "2026-10-11T02:43:31Z"
}
```

### Formato do lease

Quatro segmentos separados por `.`:

```text
pdv-offline-lease-v1 . <key_id> . base64url(payload_json) . base64url(assinatura)
```

- `base64url` sem padding, alfabeto `-_`;
- a assinatura Ed25519 cobre exatamente a string
  `"pdv-offline-lease-v1" + "." + key_id + "." + payload_b64url`;
- **verifique a assinatura sobre o texto codificado e só então decodifique o
  payload.** Nunca re-serialize o JSON para verificar: a assinatura cobre bytes,
  e a ordem dos campos é parte do contrato.

Payload, nesta ordem:

```json
{"format_version":1,"key_id":"...","session_id":"...","operator_id":"...",
 "terminal_id":"...","installation_id":"...","tenant_id":"...","company_id":"...",
 "branch_id":"...","machine_credential_reference":"...",
 "authorization_version":"...","capabilities":["pdv:operator:session"],
 "issued_at":"...","expires_at":"..."}
```

`machine_credential_reference` é o IDENTIFICADOR do PAT de máquina, não a
credencial. Compare-o com o id que você recebeu na confirmação da rotação para
detectar que o lease envelheceu.

`authorization_version` é uma impressão digital opaca da autorização efetiva, não
um contador. Guarde-a e compare: se mudar, a autorização mudou e o lease anterior
está velho.

O lease **não** contém senha, credencial de sessão, credencial de máquina nem
hash de qualquer uma delas.

### Regras de verificação no cliente

1. os quatro segmentos existem e o primeiro é exatamente `pdv-offline-lease-v1`;
2. `key_id` é uma chave que você confia (pinada na instalação);
3. a assinatura verifica com a chave pública daquele `key_id`;
4. `format_version` é suportada;
5. `expires_at` está no futuro pelo relógio local **monotônico** desde a última
   sincronização — relógio ajustado para trás ou para frente não compra prazo;
6. `session_id`, `operator_id`, `terminal_id` e `installation_id` batem com o
   estado local;
7. `capabilities` contém o que a operação exige. **Nunca** amplie a lista
   localmente;
8. `authorization_version` bate com a última conhecida.

Falhar qualquer item: trate como SEM autorização offline, não como autorização
parcial.

### Chaves

Fixe a chave pública na instalação, por canal confiável. O `public_key` da
resposta serve para DETECTAR rotação (`key_id` diferente), não como fonte de
confiança inicial. A chave privada existe só no backend. `APP_KEY` não é usada e
não deve ser distribuída.

Rotação de chave: o backend passa a emitir com outro `key_id`. Aceite as duas
enquanto houver leases antigos em prazo; recuse `key_id` desconhecido.

### Test vector sintético

Chave derivada de um seed **público e constante**, apenas para teste. Não use em
nada real.

```text
seed (hex)        = 2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a
public_key (b64)  = GX9rI+FshTLGq8g4+s1ep4m+DHaykgM0A5v6iz02jWE=
```

```text
payload_json =
{"format_version":1,"key_id":"sample-2026-1","session_id":"01K7B0QS3XJ7V5M9N2H4T6W8Y0","operator_id":"01K7B0QS3XJ7V5M9N2H4T6W8Y1","terminal_id":"01K7B0QS3XJ7V5M9N2H4T6W8Y2","installation_id":"3f1c9a52-6b7d-4e18-9c0a-5d2e8f4b1a67","tenant_id":"01K7B0QS3XJ7V5M9N2H4T6W8Y3","company_id":"01K7B0QS3XJ7V5M9N2H4T6W8Y4","branch_id":"01K7B0QS3XJ7V5M9N2H4T6W8Y5","machine_credential_reference":"4211","authorization_version":"9f86d081884c7d659a2feaa0c55ad015","capabilities":["pdv:operator:session"],"issued_at":"2026-10-11T12:00:00Z","expires_at":"2026-10-11T14:00:00Z"}
```

```text
lease =
pdv-offline-lease-v1.sample-2026-1.eyJmb3JtYXRfdmVyc2lvbiI6MSwia2V5X2lkIjoic2FtcGxlLTIwMjYtMSIsInNlc3Npb25faWQiOiIwMUs3QjBRUzNYSjdWNU05TjJINFQ2VzhZMCIsIm9wZXJhdG9yX2lkIjoiMDFLN0IwUVMzWEo3VjVNOU4ySDRUNlc4WTEiLCJ0ZXJtaW5hbF9pZCI6IjAxSzdCMFFTM1hKN1Y1TTlOMkg0VDZXOFkyIiwiaW5zdGFsbGF0aW9uX2lkIjoiM2YxYzlhNTItNmI3ZC00ZTE4LTljMGEtNWQyZThmNGIxYTY3IiwidGVuYW50X2lkIjoiMDFLN0IwUVMzWEo3VjVNOU4ySDRUNlc4WTMiLCJjb21wYW55X2lkIjoiMDFLN0IwUVMzWEo3VjVNOU4ySDRUNlc4WTQiLCJicmFuY2hfaWQiOiIwMUs3QjBRUzNYSjdWNU05TjJINFQ2VzhZNSIsIm1hY2hpbmVfY3JlZGVudGlhbF9yZWZlcmVuY2UiOiI0MjExIiwiYXV0aG9yaXphdGlvbl92ZXJzaW9uIjoiOWY4NmQwODE4ODRjN2Q2NTlhMmZlYWEwYzU1YWQwMTUiLCJjYXBhYmlsaXRpZXMiOlsicGR2Om9wZXJhdG9yOnNlc3Npb24iXSwiaXNzdWVkX2F0IjoiMjAyNi0xMC0xMVQxMjowMDowMFoiLCJleHBpcmVzX2F0IjoiMjAyNi0xMC0xMVQxNDowMDowMFoifQ.hV4EN28J6vbMRkO75aKQXq4kziSHRuhZbEP3jw_GwFz1FXL7JDZ9BksXA872UexC8U0pmEz7wP-7eo58uo-_DA
```

Casos negativos que o verificador precisa recusar (todos verificados no backend):
capacidade ampliada; `branch_id`, `tenant_id` ou `operator_id` trocados;
`expires_at` estendido; `key_id` trocado; assinatura produzida por outra chave.

### Capacidades offline nesta fase

Apenas `pdv:operator:session`. Venda, desconto, pagamento, cancelamento, sangria
e fechamento **não** são autorizados offline — nem online — nesta fase. A
estrutura de autorização existe; as capacidades comerciais serão definidas nas
fases correspondentes. Não invente capacidade local.

## 11. Rotas administrativas (humanas)

Não são chamadas pelo PDV. Documentadas para quem constrói a tela de
administração.

Cabeçalhos: `Authorization: Bearer <PAT_HUMANO>` com `business:write`, mais
`X-Tenant-ID: <TENANT>` (aqui o cabeçalho é seleção humana VALIDADA contra o
vínculo, nunca prova de autorização).

| Método | Rota | Autoridade | Sucesso |
|---|---|---|---|
| GET | `/api/v1/pdv/admin/branches/{branch}/operators` | `view-branches`, `view-all-branches` ou `manage-branches` | 200 |
| POST | `/api/v1/pdv/admin/branches/{branch}/operators` | `manage-branches` + contenção de delegação | 201 nova, 200 já ativa |
| DELETE | `/api/v1/pdv/admin/branches/{branch}/operators/{user}` | idem | 204 |
| GET | `/api/v1/pdv/admin/operators/{user}/branches` | leitura | 200 |

POST: `{"user_id": "<ULID>"}`. Campo desconhecido recusa com 422.

```json
{"data": {"branch_id": "01M4...", "operators": [
  {"id": "01M4...", "branch_id": "01M4...", "operator_id": "01M4...",
   "status": "ACTIVE", "granted_by": "01M4...",
   "granted_at": "2026-10-11T00:43:31+00:00", "revoked_at": null,
   "operator": {"id": "01M4...", "name": "...", "email": "..."}}]}}
```

Toda recusa é 403 `forbidden` genérico — não existe resposta que diferencie "não
existe" de "não é sua". A revogação encerra, na mesma transação, as sessões
daquela pessoa naquela filial.

Conceder `access-pdv` é passo separado e pertence ao RBAC existente: a permissão
está no catálogo de cada estabelecimento e em nenhum papel padrão; o
administrador a atribui a um papel próprio de caixa. **Acesso ao PDV exige a
permissão E a atribuição à filial.**

## 12. Checklist de implementação

1. ter credencial de máquina com `pdv:operator-session` (rotacionar se legada);
2. fixar a chave pública do lease, se o offline for usado;
3. login → persistir a credencial de sessão em armazenamento seguro ANTES de
   seguir;
4. guardar `session.expires_at`, `capabilities`, `authorization_version` e o
   lease;
5. mandar as duas credenciais em toda operação protegida;
6. revalidar a cada 10–15 minutos e ao reconectar;
7. tratar `operator_session_invalid` como fim do turno: limpar estado e exigir
   login;
8. tratar 409 como "outro operador no caixa", com ação humana;
9. offline só com lease válido, dentro do prazo e das capacidades;
10. logout ao fim do turno, e repetir até receber 204;
11. nunca registrar credencial, senha ou lease; nunca enviar credencial de sessão
    fora do cabeçalho.

Não altere o repositório Java a partir deste documento sem revisão: ele descreve
o contrato, não a implementação do cliente.
