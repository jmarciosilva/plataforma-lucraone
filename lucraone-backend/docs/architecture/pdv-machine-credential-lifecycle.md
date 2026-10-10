# Ciclo de vida da credencial de máquina do PDV

Estado: implementado nesta branch; integração Java e implantação pendentes de revisão.
PDV-BE-01 a PDV-BE-05 permanecem concluídos. Esta evolução não cria fase Java.
Fase 5 Java: EM ANDAMENTO / PARCIAL. Operador pendente; Fase 6 não iniciada.

## Decisão arquitetural

A substituição imediata (A) perde a credencial se a resposta se perder. A rotação
em duas etapas (B) preserva a anterior, mas uma resposta perdida exige decidir como
reentregar uma candidata que só existe como hash. Adotamos B com recuperação
verificável da entrega (C): antes da requisição o Java gera e persiste no SecretStore
um UUID v4 `operation_id` e 32 bytes CSPRNG, codificados em 64 caracteres hexadecimais
minúsculos (`delivery_secret`). Não é PIN nem senha. Não usar UUID como segredo.

O servidor deriva o segredo do PAT candidato por HMAC-SHA256, com chave do
Encrypter Laravel e separação de domínio por versão, tipo de operação, terminal,
UUID da operação e segredo de entrega. Guarda apenas SHA-256 do PAT e do segredo
de entrega. Banco + chave de servidor, sem o segredo do cliente, não recompõem o
PAT. O servidor não aceita token escolhido pelo cliente e não cifra tokens no banco.

Repetir o mesmo UUID e segredo, usando o mesmo PAT anterior ainda válido,
reentrega o MESMO candidato durante a pendência; não cria outro token nem estende
prazos. Um UUID diferente ou segredo divergente recebe 409. Depois da confirmação,
o PAT anterior não autentica mais. O cliente usa o candidato para repetir a
confirmação e descobrir o vencimento definitivo; não repete a preparação.

A candidata tem somente `pdv:credential:confirm` e TTL máximo de 10 minutos.
Não consulta o terminal nem renova. A confirmação promove esse mesmo PAT para
`pdv:terminal:read`, `pdv:credential:renew`, `pdv:credential:confirm` e remove todos
os outros PATs SOMENTE daquele terminal. A ability de confirmação permanece para
retry idempotente da confirmação; não concede negócio, operador ou administração.

A confirmação redefine `created_at` e `expires_at` somente na primeira promoção,
iniciando um novo prazo Sanctum. Repetições não renovam esse prazo. É permitida
após expiração do anterior, enquanto a candidata estiver válida e a linha anterior
existir; revogação oficial invalida pendências e remove ambos.

## Expiração efetiva

O limite é o menor entre `expires_at` e `created_at + sanctum.expiration`, quando
o teto global está habilitado (> 0). Nenhuma configuração global é aumentada ou
removida. `MachineCredentialExpiration` calcula tanto emissão quanto divulgação;
o GET existente mantém exatamente suas chaves, publicando agora o prazo efetivo.
A emissão inicial e a promoção usam no máximo 720 minutos, ou o teto global menor.

O Java deve consultar o prazo, renovar antecipadamente e evitar depender do último
minuto. Recomendação: iniciar pelo menos 15 minutos antes, ou com 20% do TTL
restante para configurações curtas. Revalidar o prazo após confirmação/reconexão.
Uma candidata vencida nunca é promovida. Relógio do cliente não autoriza extensão.

## Compatibilidade e transição

Health e pairing mantêm os contratos. `PdvTerminalPayload` não foi alterado.
Credenciais iniciais continuam com exatamente `pdv:terminal:read`; não há atualização
em massa dos PATs existentes. A preparação possui exceção explícita de transição:
PAT REAL de Terminal, nome configurado `pdv-machine`, lista EXATA `[pdv:terminal:read]`,
ainda válido e terminal/estrutura elegíveis. Outros tokens read-only não renovam.
PAT com coringa é recusado. Tokens promovidos usam a nova ability de renovação.
Tokens humanos continuam isolados. Nenhum cookie substitui PAT nos endpoints novos.

## Contratos implementados

Prefixo `/api/v1/pdv`. JSON; sucessos em `data`; erros no envelope PDV existente.
Todas as respostas sensíveis têm `Cache-Control: no-store, private` e
`Pragma: no-cache`. `X-Request-ID` é normalizado/preservado pelo middleware existente;
o mesmo valor consta no erro. Não é chave de idempotência. Não enviar token por
query string, contexto empresarial arbitrário ou campos desconhecidos.

### POST /terminal/credential/rotations

Middleware: `auth:sanctum` -> `terminal.context` -> `throttle:pdv-credential` ->
`EnsureCredentialAbility:renew`. Ability: `pdv:credential:renew` ou exceção legada
restrita acima. Terminal derivado do PAT, ACTIVE, instalação existente e estrutura
operacional/coerente. Cookie, token humano e X-Tenant-ID são recusados.

Request sintético (segredo é placeholder; deve ser 64 hex CSPRNG):

```json
{"operation_id":"00000000-0000-4000-8000-000000000001","delivery_secret":"<64_HEX_CSPRNG>"}
```

Response 200 sintética, incluindo retries:

```json
{"data":{"rotation":{"id":"<OPERATION_UUID>","status":"PENDING","expires_at":"<UTC_INSTANT>"},"credential":{"token_type":"Bearer","access_token":"<CANDIDATE_PAT>","expires_at":"<UTC_INSTANT>"}}}
```

`rotation.expires_at` e `credential.expires_at` nesta resposta representam a janela
curta de confirmação, não a validade operacional futura. Uma pendência por terminal.
Limitador compartilhado com confirmação: 12/minuto por terminal. Sem TTL deslizante.

Erros: 401 `unauthenticated`; 403 `forbidden`; 409 `credential_conflict`;
422 `validation_error`; 429 `rate_limited`; 500 `internal_error`.

Idempotência limitada: mesma operação/segredo/PAT anterior dentro da pendência
retorna mesmo PAT, sem nova emissão, auditoria duplicada ou extensão do prazo.
Após expiração/invalidação: conflito; novo UUID somente após fim da pendência,
enquanto o PAT anterior ainda for válido. Não há endpoint de cancelamento.

### POST /terminal/credential/confirm

Middleware igual, com `EnsureCredentialAbility:confirm`; exige PAT de candidata
ou PAT já promovido da MESMA operação e terminal. Ability `pdv:credential:confirm`.
Request: `{"operation_id":"<OPERATION_UUID_V4>"}`.

Response 200:

```json
{"data":{"rotation":{"id":"<OPERATION_UUID>","status":"CONFIRMED","expires_at":"<ORIGINAL_PENDING_DEADLINE>"},"credential":{"token_type":"Bearer","expires_at":"<OPERATIONAL_UTC_DEADLINE>"}}}
```

Não reentrega segredo. `rotation.expires_at` é a janela histórica da pendência;
validade atual vem de `credential.expires_at`. Confirmar novamente retorna o mesmo
vencimento, sem estender prazo nem repetir auditoria. UUID errado: 409. Candidata
expirada/revogada: 401. Estrutura inelegível: 401. Mesmos limites e erros da preparação.

### POST /admin/terminals/{terminal}/credential-recovery-codes

Middleware: `auth:sanctum` -> `RecoveryAdministrationContext` ->
`throttle:pdv-recovery-admin`. Exige PAT humano real com `business:write`, conta e
membership ativas, tenant resolvido pelo mecanismo humano e `TerminalPolicy::update`
(autoridade atual `manage-branches`). Não é login do operador; não cria nova role.
Sem bypass por Platform Admin ou nome do papel. X-Tenant-ID aqui é seleção HUMANA
validada, nunca prova de autorização. Terminal fora do tenant: 403 genérico.

Request: `{"reason":"lost"}`. Razões aceitas: `expired`, `lost`, `compromised`,
`keyring-unavailable`, `rotation-failed`. Nenhum texto livre de segredo na auditoria.

Response 200:

```json
{"data":{"recovery_code":"<SELECTOR.SECRET>","expires_at":"<UTC_INSTANT>"}}
```

Código: formato de 19 caracteres, gerado por CSPRNG, selector público de 6 e segredo
de 12. Hash somente do segredo; tabela e finalidade distintas do pairing. TTL máximo
10 minutos, limitado ainda pelo teto global menor. Somente terminal ACTIVE já
instalado e estrutura operacional. REVOKED não é reativado; BLOCKED exige fluxo
administrativo prévio de desbloqueio já autorizado, fora deste endpoint.

Na AUTORIZAÇÃO, a transação invalida códigos/pendências anteriores e remove todos
os PATs do terminal: uma credencial possivelmente comprometida não permanece ativa.
Falha de banco desfaz também essa revogação. Auditoria registra ator humano,
terminal, tenant, operação e correlação, sem código/token/hash/prova.

Rate limit: 5/10 minutos por usuário. Erros: 401 `unauthenticated`; 403 `forbidden`;
422 `validation_error`; 429 `rate_limited`; 500 `internal_error`.
Não idempotente: resposta perdida exige NOVA autorização, que invalida a anterior.
Respeitar 429; não reemitir em loop. Interface administrativa gráfica não incluída.

### POST /terminal/credential/recover

Público porque a credencial anterior não existe/expirou. Middleware `throttle:pdv-recovery`.
A prova é o código administrativo válido, não terminal_id/installation_id. Não aceita
X-Tenant-ID ou seleção de terminal/contexto. Instalação deve coincidir com a conhecida.

```json
{"recovery_code":"<SELECTOR.SECRET>","installation_id":"<KNOWN_UUID_V4>","delivery_secret":"<64_HEX_CSPRNG>"}
```

Persistir segredo no SecretStore ANTES do POST. Response 200: mesmo formato PENDING
da preparação. `rotation.id` vem do servidor. O código cria no máximo uma candidata;
seu consumo vincula a operação ao hash do segredo de entrega. Repetição com mesmo
código/instalação/segredo reentrega a mesma candidata dentro do prazo original.
Segredo diferente não cria segunda candidata. Após confirmação, código nunca reentrega
credencial. Confirmação é o endpoint autenticado acima.

Código inexistente/expirado/consumido/inválido, instalação divergente e terminal
inelegível: 422 `recovery_failed`, sem diferenciar causas. Cinco tentativas incorretas
persistentes invalidam o código e eventual candidata. Limites HTTP complementares:
20/10 minutos por IP e 10/10 minutos por selector, permitindo retries válidos dentro
da janela. Chave de cache nunca usa código completo ou segredo de entrega.
Outros erros: 422 `validation_error`, 429 `rate_limited`, 500 `internal_error`.

## Procedimento Java e falhas

1. Persistir UUID/segredo no SecretStore com namespace da instalação antes de preparar.
2. Manter a credencial anterior; chamar preparação e persistir candidata separadamente.
3. Somente após persistência durável, confirmar usando Bearer da candidata.
4. Resposta de confirmação perdida: repetir confirmação com candidata; NÃO voltar ao PAT antigo.
5. Após sucesso, atualizar vencimento operacional, promover candidata local e remover anterior/prova.

Se a confirmação teve resultado ambíguo, manter a candidata protegida mesmo que
o prazo PENDING local tenha passado: uma confirmação já commitada pode ter
promovido o PAT por 720 minutos. Na reconexão, repetir confirmação online para
resolver a ambiguidade; não operar offline além do prazo local conhecido. Se o
servidor responder 401, buscar recuperação administrativa.
6. Reinício: restaurar pendência/prova/candidata protegidas e seguir o mesmo protocolo.

Resposta perdida na preparação: retry exato com PAT antigo/prova; sem novo UUID.
Se antiga venceu e candidata não chegou, recuperação administrativa. Se candidata
chegou, pode confirmar até o vencimento pendente mesmo com antiga expirada.
Falha ao persistir SecretStore: NÃO confirmar. Antiga permanece; aguardar TTL ou
solicitar recuperação. Se segredo/candidata foram perdidos, não há endpoint anônimo
para recuperar por installation_id. Keyring definitivamente indisponível exige
restabelecer armazenamento seguro antes de emitir/persistir qualquer credencial.

Recuperação: administrador autorizado verifica a instalação real, emite código e
entrega por canal protegido àquela instalação; Java persiste segredo, consome,
persiste candidata e confirma. Resposta perdida do consumo admite retry exato.
Resposta perdida da emissão do CÓDIGO exige nova emissão administrativa.
Código vencido/candidata vencida/segredo perdido: nova autorização administrativa.
Não recria terminal, não altera installation_id/tenant/company/branch nem histórico.
Substituição física de equipamento continua um processo administrativo distinto.

| Resultado | Ação Java |
|---|---|
| Timeout/rede/5xx | Backoff com jitter; mesmo request/segredo; nunca confirmar sem persistir candidata |
| 401 | Não autenticar offline por este erro; candidata válida pode confirmar; caso contrário recuperar administrativamente |
| 403 | Não alterar abilities/contexto; revisar credencial ou autorização |
| 409 | Resolver pendência local; não gerar UUIDs em loop; aguardar TTL/recuperar |
| 422 validation_error | Corrigir schema, sem eco de segredos |
| 422 recovery_failed | Nova autorização se código não puder ser usado; não sondar existência |
| 429 | Respeitar Retry-After e prazo restante; buscar suporte se prazo se esgotar |

## Concorrência, revogação e limpeza

Transações serializam pelo terminal, depois estrutura operacional e registros de
operação/PAT. Revalidam estado e prazo dentro do lock. Há locks MySQL reais e testes
multiprocessos. Não existem chamadas externas lentas dentro da transação.
Falha antes do commit não deixa candidata/promocão/revogação parcial; falha de
entrega depois do commit é tratada pelo protocolo. Dois UUIDs concorrentes: um
vence; mesma operação: mesma candidata. Dois confirmadores: um promove, outro lê
CONFIRMED. Bloqueio/revogação oficial remove tokens e invalida pendências.
Recuperação e renovação não alcançam PAT humano ou de outro terminal.

TTL é aplicado pela autenticação e serviço, independente da limpeza física.
`php artisan pdv:expire-credential-operations` remove somente candidatas de operações
ISSUED/PENDING vencidas e marca EXPIRED; mantém histórico. Preparação também faz
limpeza do terminal sob lock. Comando incluído, não executado em produção, não
agendado automaticamente. Agendar depois do deploy/migration em ambiente aprovado.
Sem limpeza, registros vencidos não autenticam, mas exigem política de retenção.

## Segurança e limitações

TLS obrigatório na operação real; nenhum TLS/proxy foi alterado. Corpo, Authorization,
segredo de entrega, códigos e tokens não devem ser registrados por Java, proxy,
APM ou tracing. Erros das rotas novas registram somente tipo de exceção e correlação,
sem exceção original/stack/SQL/argumentos. Exceções de domínio previstas não são logadas.
`dontFlash` inclui código e segredo. DTO de credencial censura debug.

Credencial roubada ainda representa terminal: não existe prova de hardware,
mTLS ou detecção de clonagem. Iniciar recuperação revoga imediatamente possível
credencial comprometida. O UUID da instalação é preservado e não é segredo.
Rotação de APP_KEY durante pendência impede recompor candidata ainda não entregue;
PAT já entregue continua autenticável por hash. Aguardar prazo ou autorizar recuperação.
SQL direto/bulk update/saveQuietly não executam hooks oficiais: estado inelegível
continua negado por requisição; não usar SQL direto para bloqueio/desbloqueio administrativo.
Pruning externo do PAT anterior antes da confirmação torna necessária recuperação.

## Banco, implantação e rollback

Migration aditiva `2026_10_10_160000_create_terminal_credential_operations_table`.
Não modifica PATs ou terminais existentes; registra snapshots, hashes, prazos,
estados e referência histórica de PAT sem cascata. AuditLog é reaproveitado com ator
explícito. Não armazena password ou access token legível/criptografado recuperável.

Revisar e homologar, aplicar migration ANTES do código e habilitar integração Java
somente depois. Esta branch não foi implantada. Rollback recomendado: código anterior,
manter tabela/histórico; encerrar pendências sob controle antes de rollback operacional.
`down()` existe e foi exercitado no MySQL descartável, mas destrói metadados e não é
rollback operacional sem perda. Não executar down para apagar histórico de produção.

## Validação e reprodução isolada

SQLite em memória, cache/session array, mail array, fila sync, sem rede. Baseline
prístino foi medido a partir do HEAD anterior, com .env somente sintético e cópia
de assets existentes. Dependências do volume operacional foram montadas somente
leitura; nenhum Artisan foi executado no container operacional.

Suite MySQL separada: `phpunit.mysql-lifecycle.xml`, bootstrap opt-in
`PDV_DISPOSABLE_MYSQL=1`, host `pdv-lifecycle-mysql`, database `pdv_lifecycle_test`,
rede interna dedicada, sem portas e dados tmpfs. Password é fornecida somente ao
container descartável. Não usar config padrão que aponte para banco operacional.
Executar `vendor/bin/phpunit -c phpunit.mysql-lifecycle.xml --do-not-cache-result`
EXCLUSIVAMENTE nesse ambiente. DatabaseMigrations recria apenas essa base descartável.

Regressão final SQLite: 1.512 testes, 1.510 aprovados, nenhuma falha/erro/skipped,
dois risky preexistentes (baseline: 1.458 testes, 1.456 aprovados, dois risky).
Foram acrescentados 54 testes HTTP/domínio; recorte PDV/Terminals: 261 aprovados.
MySQL 8.4 descartável: oito testes de concorrência aprovados, 76 asserções, com
processos/conexões independentes e migrations up/down. Uma repetição intermediária
sofreu OOM no container descartável; a execução final passou após recriação e
redução de memória de buffers/logs, sem alteração de serviços operacionais.
Pint: 23 arquivos PHP aprovados. PHPStan mantém os 16 diagnósticos de arquivo e
um ignore não correspondente do baseline, sem diagnóstico novo.

Não iniciar autenticação do operador ou Fase 6 Java nesta evolução.
