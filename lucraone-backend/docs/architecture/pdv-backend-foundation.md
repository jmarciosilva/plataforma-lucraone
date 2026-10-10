# PDV-BE — Fundação, Terminal e pairing

**Trilha:** PDV-BE · **Data:** 2026-10-10
**Estado:** trilha completa — PDV-BE-01 a PDV-BE-05 implementados; os três contratos HTTP do PDV estão no ar e validados em staging.
**Commit funcional PDV-BE-01:** `2d203951b2e824234b53b0e675ffe8e5b82bf5c3`.
**Commit funcional PDV-BE-02:** `1e96b207a102d81ed089ab1f7693ef2cc6aadb89`.
**Commit funcional PDV-BE-03:** `6887d60803fc9989b8623c6c2cc2b12a80b9dfd8`.
**Commit funcional PDV-BE-04:** `316db631c4f4e9f5c42132cdf9ae92b05268ccc1`.
**Commit funcional PDV-BE-05:** `be4058748bba6e77030a4df109e171d1179ae3cf`.

**Validação histórica PDV-BE-01:** 13 testes BranchPolicy (48 assertions) e 14 testes de correlação
(49 assertions), todos PASS. Suíte completa: 1212 testes, 1210 PASS, 0 FAIL/ERROR,
2 RISKY preexistentes, 0 SKIPPED e 4802 assertions. Staging validado com geração e
preservação de X-Request-ID. Nenhuma migration nesta etapa.

## Contexto e fronteira da entrega

PDV-BE prepara o backend para integrar uma instalação operacional do LucraOne
PDV Java. É uma trilha própria, independente da F2.6 — Integration APIs, que
continua **liberada e não iniciada**. Concluir PDV-BE-01 não torna o backend
apto para PDV. O marco exige Terminal, vínculos, pairing, credencial e autorização
de máquina, endpoints reais, testes e validação de staging.

Conforme o estado informado pelo responsável pelo produto, as Fases 1–3 do Java
PDV estão concluídas. A Fase 4 permanece planejada, aguardando os contratos reais
do backend; este documento não autoriza iniciar integração real nem altera o Java.

No PDV-BE-01 foram corrigidos BranchPolicy e correlação da API. No PDV-BE-02
foram implementados Terminal, migration, vínculos e Policy, conforme abaixo.
No PDV-BE-03 foi implementado o domínio de pairing/provisionamento, sem HTTP
ou credencial. No PDV-BE-04 foram implementados a política de sujeito do Sanctum,
o Terminal autenticável, a credencial de máquina com ability própria, o contexto
de máquina e o enforcement por requisição — e o pairing passou a emitir a
credencial. No PDV-BE-05 os três contratos HTTP foram expostos, com freio do
pareamento, contrato público de erro e validação ponta a ponta em staging.
Branch API continua ausente. O marco de aptidão para a integração inicial do PDV
está atingido; operação comercial do PDV depende de contratos funcionais que
ainda não existem.

## Base existente: Tenant, Company, Branch e User

Tenant, Company, Branch e o User modular usam IDs de 26 caracteres, utilizados
como ULID. User é uma identidade humana global, com vínculos em `tenant_user`.
Tenant e User têm soft delete; Company e Branch não têm. Company e Branch usam
`HasTenant` e `TenantScope`; User não tem `tenant_id` próprio.

Company pertence a Tenant por `tenant_id` e possui `branches()`. Branch existe em
`App\Modules\Branches\Domain\Models\Branch`, com `tenant_id`, `company_id`,
`name`, `code`, `document_override`, `email`, `phone`, `timezone` e `status`.
Suas FKs individuais apontam para Tenant e Company. Os status de Company e
Branch são `ACTIVE`, `INACTIVE`, `SUSPENDED`. Branch é o alvo operacional
coerente com o modelo existente: filial de uma empresa de um cliente lógico SaaS.

**Invariável de domínio:** `branch.tenant_id` deve corresponder ao
`company.tenant_id` da empresa indicada por `branch.company_id`. As FKs atuais
não garantem essa igualdade por constraint composta. O accessor `$branch->company`
filtra a empresa pelo tenant da filial; a relação explícita `company()` isolada
não acrescenta essa igualdade e depende do escopo/contexto. Nenhum schema mudou.
Futuros fluxos de criação/alteração deverão validar essa invariável na escrita.

### Autorização de Branch

A Policy anterior não estava registrada, importava um model inexistente em
`Modules/Companies` e chamava `User->branches()`, relação que não existe. Declarava
`viewAny`, `view`, `create`, `update`, `delete`; não havia controller consumidor
nem teste direto. Existem testes anteriores de relações/isolamento de Company.

O catálogo `StandardRoleMatrix` já possui `view-branches`, `view-all-branches`
e `manage-branches`. Portanto Branch **não herda** `manage-companies` e nenhuma
permissão nova foi criada. A Policy corrigida é registrada em AppServiceProvider:

| Ação | Autoridade existente |
|---|---|
| Listar e ler | `view-branches`, `view-all-branches` ou `manage-branches` |
| Criar, atualizar e excluir | `manage-branches` |

Todas exigem contexto resolvido, conta ativa e vínculo ativo naquele tenant.
Na instância, `Branch.tenant_id` deve coincidir com o contexto e as permissões
são avaliadas explicitamente nesse tenant. Leitura/alteração/exclusão recusam
uma filial cuja empresa não seja encontrada no mesmo tenant. `create`, sem
instância/payload, autoriza a capacidade no contexto; não substitui validação
futura de `company_id` no fluxo de criação.

O admin padrão pode gerir; manager/viewer podem ler conforme suas permissões;
`manage-companies` sozinho não concede gestão de Branch. Permissões antigas
`*-assigned-branches` permanecem no catálogo, sem conceder acesso por esta Policy:
não há modelo de atribuição User ↔ Branch. Não foi adicionado tal relacionamento.

## Correlação da API

`RequestCorrelationMiddleware` é registrado uma vez por `api(prepend: ...)` em
`bootstrap/app.php`, antes dos middlewares da API. A ordem das rotas de negócio
continua `auth:sanctum` → `token.ability` → `tenant`. Cobre `/api/health`, login,
logout e APIs de negócio, inclusive erros renderizados dentro desse pipeline.

Política do header: aceitar exatamente 1–128 caracteres ASCII alfanuméricos e
`._:-`, preservando o valor recebido. UUID, ULID e `pdv-be-01-smoke` são aceitos.
Ausência, vazio, controles, espaços ou excesso de tamanho geram um novo ULID;
não causam erro adicional. O ID é opaco e público, não autentica e não é segredo.

O mesmo valor é colocado no Request, no response header e em
`Log::withContext(['request_id' => ...])`. O contexto de log é removido em
`finally` para não vazar para a próxima execução. O pipeline do Laravel converte
exceções internas em respostas, permitindo que o middleware devolva o header
também em 401, 403, 404 de entidade e 422. Uma URL sem rota correspondente não
entra no grupo API; correlação de 404 de roteamento/global fica fora desta entrega.

AuditLog já lê o header e passa a compartilhar o ID na API, sem alterar seu
fallback para execução fora desse pipeline. StructuredLoggingService também lê
o header, mas seu construtor contém uma referência antiga a
`app('TenantContext')->getTenantId()`, incompatível com o registro/API atuais.
Esse serviço não foi redesenhado nem ativado nesta rodada. O teste comprova o
logger Laravel ativo e AuditLog, não promete funcionamento daquele construtor.
Fallbacks independentes de ULID ainda existem fora da API registrada.

## Terminal: papel e vínculos implementados no PDV-BE-02

Terminal representa **uma instalação operacional do LucraOne PDV**, com identidade
própria. Não é User, não reutiliza e-mail/senha de uma pessoa e não pertence ao
modelo humano de memberships/RBAC. ID ULID implementado com HasUlid e cast string, coerente com o backend.

Terminal pertence obrigatoriamente a `tenant_id`, `company_id` e `branch_id`.
Branch é o alvo operacional principal; Company é a empresa jurídica/comercial;
Tenant é o cliente lógico do SaaS. Mesmo que Branch implique os demais vínculos,
mantê-los explícitos favorece consultas, auditoria, autorização, cache/sync local
e verificação operacional. A redundância exige validação; não é permissão para
aceitar três IDs independentes fornecidos pelo PDV.

Invariantes implementadas na escrita Eloquent de Terminal:

- `terminal.tenant_id == branch.tenant_id`;
- `terminal.company_id == branch.company_id`;
- Company da Branch pertence ao mesmo tenant.

Fronteiras operacionais futuras, ainda sem machine auth ou administração:

- Terminal não poderá operar fora da sua Branch, mesmo manipulando IDs/header;
- mudança de filial exigirá procedimento administrativo explícito, credenciais e
  estado local revistos; não será uma troca livre de contexto pelo cliente.

### installation_id

O Java PDV já gera UUID v4 estável por instalação, conforme informado pelo produto.
Será enviado no pairing e associado ao Terminal. É identificador, **não segredo
nem prova de posse**. Unicidade global entre instalações vinculadas foi implementada para
impedir que uma instalação ativa seja associada a dois Terminais. Terminal
pré-cadastrado nasce com `installation_id` NULL; múltiplos NULLs são permitidos.
O validador aceita somente UUID v4 e canonicaliza letras para minúsculas, evitando
unicidade diferente entre SQLite (comparação sensível à caixa) e MySQL.

Reinstalação, substituição de hardware e reatribuição de installation_id exigem
decisão explícita antes do pairing; um ID antigo não deve reativar uma
identidade revogada. A coluna nullable e o unique global existem no PDV-BE-02.
No PDV-BE-03 o primeiro UUID não nulo persistido passa a ser imutável no fluxo
Eloquent. Consumo associa instalação e ativa o Terminal atomicamente; tentativa
de troca/remoção posterior é recusada. A mesma UUID com caixa diferente mantém
a identidade canonicalizada. Re-pairing/recuperação ainda não têm fluxo.

### Status formalizado no PDV-BE-02

| Status | Significado | Transição conceitual |
|---|---|---|
| `PENDING` | Pré-cadastrado, sem operação | Pairing válido habilita `ACTIVE` |
| `ACTIVE` | Pareado e autorizado a operar | Administração pode bloquear/revogar |
| `BLOCKED` | Bloqueio administrativo reversível | Reativação controlada; tokens permanecem sujeitos ao status |
| `REVOKED` | Identidade retirada de operação | Revogar credenciais; novo provisionamento explícito |

Os nomes distinguem provisionamento, operação, suspensão reversível e revogação.
São constantes string no model e enum SQL, seguindo Company/Branch, sem enum
PHP novo. Default PENDING no model e no banco; ACTIVE exige installation_id,
sem emitir credencial nem comprovar pairing. REVOKED não pode mudar para outro
status pelo model. Não há soft delete: REVOKED representa retirada operacional.
Policy não autoriza exclusão física. Retenção histórica e transições completas
ainda deverão ser fechadas antes dos fluxos administrativos e de pairing.

## Persistência e autorização implementadas no PDV-BE-02

Schema mínimo: id ULID, tenant_id/company_id/branch_id char(26) obrigatórios,
installation_id UUID nullable/unique global, name varchar(255) obrigatório,
status enum com default PENDING, created_at/updated_at. Nome identifica o caixa
na administração, sem unicidade/normalização inventada. Não há requisito de
código humano separado; code, paired_at e last_seen_at ficam adiados até haver
consumidor. Não há tokens, segredos ou HasApiTokens no model.

Terminal usa HasFactory, HasUlid e HasTenant/TenantScope. Expõe tenant(),
company(), branch(); os três pais expõem terminals(). Relações suportam eager
loading. TenantScope filtra quando há contexto, conforme o padrão existente;
sem contexto não é uma barreira de autorização. A Policy exige contexto.

Um pequeno evento saving delega a TerminalAssignmentValidator, protegendo
create()/save()/update() de instâncias sem duplicar regras numa futura camada
HTTP. O validador consulta os pais sem TenantScope, mas compara explicitamente
todos os IDs: company.tenant_id == terminal.tenant_id == branch.tenant_id,
branch.company_id == terminal.company_id. Tenant não pode estar soft-deleted.
Não aplica isActive aos pais: status operacional será aplicado à máquina no
PDV-BE-04, preservando o comportamento humano atual. Rejeita vínculos ausentes
ou inconsistentes, UUID não-v4, nome vazio/maior que 255, status desconhecido,
ACTIVE sem instalação e reativação de REVOKED. Exceção de domínio:
InvalidTerminalAssignment, derivada de InvalidArgumentException.

FKs dos três pais usam RESTRICT; não apagam Terminals silenciosamente, mesmo
quando o schema dos pais tem cascades. Índices: (tenant_id,status), company_id,
branch_id; unique installation_id. Igualdades entre vínculos são validação de
domínio, não FKs compostas. SQL direto, query-builder bulk update, saveQuietly e
alterações nos pais não executam o evento de Terminal; não devem ser usados
como fluxo oficial de alteração de vínculos. Futuras operações administrativas
nos pais deverão preservar essas igualdades com estratégia transacional; os
fluxos atuais de Company/Branch não foram redesenhados nesta entrega.

Factory cria uma Branch e deriva dela Company/Tenant, PENDING/NULL por default.
forBranch() recebe uma filial existente e copia os três IDs coerentes. Não há
state paired nem simulação de pairing; fixtures podem fornecer status e UUID
explicitamente, sujeitos ao mesmo validador.

TerminalPolicy está registrada no AppServiceProvider e reutiliza BranchPolicy.
Não há view-terminals/manage-terminals no catálogo atual: leitura usa as
permissões existentes view-branches/view-all-branches/manage-branches e gestão
usa manage-branches, temporariamente. Catálogo específico deverá ser decidido
antes de expor administração. A instância deve corresponder ao contexto e seus
vínculos devem continuar coerentes; conta e membership ativos, permissões no
tenant da entidade. Ações: viewAny/view/create/update/revoke. revoke somente
autoriza futura administração; não revoga token. delete físico não autorizado.

Validação: 26 testes de domínio/45 assertions e 13 de Policy/43 assertions.
Suíte completa: 1251 total, 1249 PASS, 0 FAIL/ERROR, 2 RISKY conhecidos,
0 SKIPPED, 4890 assertions. PHPStan: os mesmos 17 achados preexistentes.
Migration 2026_10_08_120000 aplicada em staging após backup e revisão do DDL;
41 migrations executadas, 0 pendentes. SQLite e MySQL descartável validaram
unicidade/NULL, FKs e RESTRICT; rollback/reaplicação testados apenas no banco
descartável. MySQL usa enum nativo, SQLite usa CHECK para os mesmos status.
Nenhum Terminal real cadastrado; smoke saudável e correlação preservada.

PDV-BE-02 não tornou o backend apto para PDV; PDV-BE-03 também não. O domínio
de pairing está implementado abaixo; credenciais/endpoints continuam futuros.
F2.6 permanece liberada e não iniciada.

## Pairing e provisionamento implementados no PDV-BE-03

Esta entrega é domínio: IssueTerminalPairingCode e ConsumeTerminalPairingCode,
sem endpoint, UI, User/login, Gate interno ou credencial. A camada administrativa
futura deve autorizar emissão/regeneração pela TerminalPolicy. Posse do código
permite consumir somente a identidade Terminal associada ao selector; contexto
é derivado do Terminal, sem X-Tenant-ID. Isso ainda não autentica operação PDV.

### Persistência e histórico

TerminalPairingCode usa HasUlid/HasFactory, sem coluna tenant_id redundante ou
TenantScope: pertence ao Terminal por FK. Lookup do código é global por selector;
não concede leitura pública da entidade nem cria rota. Relações: Terminal
pairingCodes() e TerminalPairingCode terminal(). Não há hard delete automático.

| Campo | Função implementada |
|---|---|
| id char(26), PK | Identidade ULID do ciclo de pairing |
| terminal_id char(26), obrigatório/FK RESTRICT | Identidade operacional alvo; preservar histórico frente a delete físico |
| selector char(6), unique global | Lookup público eficiente e associação de tentativa à linha |
| code_hash char(64) | SHA-256 somente do segredo; oculto na serialização |
| expires_at obrigatório | Prazo efetivo do código |
| attempts tinyint unsigned, default 0 | Contador persistente de submissões localizáveis |
| consumed_at nullable | Uso bem-sucedido único |
| invalidated_at nullable | Regeneração ou esgotamento de tentativas |
| created_at/updated_at | Rastreabilidade do ciclo |

Índices usados: unique selector e terminal_id para histórico/invalidação. Não
há índice de hash/expiração sem consumidor. A Factory produz Terminal PENDING,
segredo aleatório somente transformado em hash, código não consumido e prazo
futuro; states expired/consumed/invalidated. Não guarda código conhecido em claro.

### Código, hash e ameaça considerada

Formato **SSSSSS.XXXXXXXXXXXX**, 19 caracteres: selector público de 6 e segredo
de 12. Alfabeto de 30 símbolos: **23456789ABCDEFGHJKMNPQRSTVWXYZ**, excluindo
0/O, 1/I/L e U. Gerado com random_int criptográfico sem viés modular. Entropia
aproximada do segredo: **58,88 bits**; selector tem 29,44 bits públicos e não é
contado como segredo. Comprimento maior que oito caracteres foi escolhido para
manter entropia e permitir contador direcionado sem Terminal ID adicional.
Entrada aceita caixa diferente, canonicalizada em maiúsculas; formato/separador
exatos, sem trim ou substituição de caracteres ambíguos.

SHA-256 do segredo + comparação hash_equals, sem pepper novo. O código aleatório
não é senha humana de baixa entropia. Selector evita scan e permite attempts;
TTL curto e limite online são obrigatórios. Padrão conceitual semelhante ao
selector/hash instalado no Sanctum, sem usar Sanctum no Terminal. Reset de
senha existente usa hasher e identificação por email, portanto não foi copiado
como protocolo de pairing. Vazamento somente-leitura do banco não entrega o
segredo; ataques offline continuam uma ameaça, reduzida pela entropia e TTL.
SHA-256 não promete proteção contra comprometimento de escrita do próprio banco.

Código em claro existe somente no retorno IssuedTerminalPairingCode da emissão,
com pairingId, code e expiresAt; não há método de recuperação. Debug do DTO
oculta code. Parâmetros sensíveis usam SensitiveParameter; mensagens de falha
não incluem código, hash, UUID, SQL ou exceção de banco anterior.

### Emissão, prazo e regeneração

config/pdv.php contém somente **ttl_minutes=10** e **max_attempts=5**, sem ENV ou
segredo novo. Terminal deve ser PENDING e installation_id NULL. ACTIVE, BLOCKED,
REVOKED ou instalação já informada são recusados; re-pairing será fluxo futuro.

Emissão relê/bloqueia Terminal, valida/bloqueia pais e invalida registros antigos
não consumidos/não invalidados, incluindo expirados preservados no histórico.
Cria novo selector/hash/prazo com attempts=0. Uma transação e lock do Terminal
serializam emissão concorrente; no fluxo oficial há no máximo um código válido.
Colisão de selector tem retry limitado com rollback, sem destruir o código
anterior. Falha na regeneração também preserva o código antigo. Model/factory,
SQL direto e alterações em lote não substituem os serviços oficiais.

### Consumo e transação

Entrada: pairing_code + installation_id. Saída TerminalProvisioningResult:
terminalId, tenantId, companyId, branchId, installationId, status; sem token,
credential ou secret. Uma UUID pública não é prova de posse de credencial.

1. Validar formato e localizar pairing por selector.
2. Bloquear Terminal **antes** do pairing; reler/bloquear pairing depois.
3. Recusar consumed, attempts esgotado, invalidated e expires_at <= agora.
4. Incrementar attempts em memória e comparar hash do segredo.
5. Validar Terminal PENDING/NULL; bloquear Tenant, Company, Branch nessa ordem
   e revalidar estados e invariantes com TerminalAssignmentValidator.
6. Normalizar UUID v4 pela regra compartilhada InstallationId e recusar vínculo
   já existente, independentemente de TenantContext.
7. Associar instalação, promover ACTIVE e marcar consumed_at/attempts.
8. Commit único; devolver resultado sem credencial.

Ordem Terminal → pairing coincide com emissão e evita deadlock por inversão
em regeneração/consumo. Os pais ficam protegidos contra atualização durante a
validação. A constraint global de installation_id é a defesa final em corrida
entre Terminals de tenants diferentes. Exceção de unicidade vira falha de domínio
sanitizada após rollback completo. Não há sucesso idempotente em replay: mesmo
código/instalação ou instalação diferente após consumo sempre falha.

Falhas de domínio localizáveis são retornadas internamente pela transação,
persistindo attempts/invalidação; PairingFailed é lançado somente após esse
commit. Falhas de infraestrutura revertem Terminal, pairing e contador, sem
estado parcial. Camada chamadora não deve envolver consume numa transação externa
que reverta attempts ao capturar a falha; serviços devem controlar esse ciclo.

### Attempts e limite de abuso

Cinco submissões permitidas para registro ainda válido. Contam erro de segredo,
UUID inválida, estrutura/Terminal recusados, instalação duplicada e sucesso.
O quinto erro invalida; a quinta submissão pode ter sucesso se válida. Limite
é persistente e não reseta ao reiniciar processo. Regeneração cria novo registro.

Selector desconhecido e código malformado não permitem atribuir tentativa à
linha. Expirado/invalidado/consumido já são inutilizáveis e não acumulam attempts.
Constraint concorrente/erro de infraestrutura faz rollback e pode não incrementar
o contador. **Não há RateLimiter de IP nesta etapa, pois não há HTTP.** O futuro
endpoint deve limitar por IP e selector/Terminal, sem segredo em chaves/logs,
usar mensagens públicas genéricas e prever recuperação administrativa.
Selector conhecido também permite DoS dirigido pelo esgotamento; attempts não
substitui limitação de IP, controles de emissão e suporte à regeneração.

### Estados operacionais e fronteira humana

Emissão e consumo usam Tenant::isActive existente: active=true e ACTIVE/TRIAL.
Tenant SUSPENDED/CANCELLED, disabled ou soft-deleted é recusado. Company/Branch
precisam existir e estar ACTIVE; INACTIVE/SUSPENDED são recusados. Invariantes
Company/Tenant e Branch/Company/Tenant são revalidadas, inclusive se um pai foi
alterado após emissão. Aceitação de TRIAL segue semântica atual, somente para
pairing; política contínua de máquina continua responsabilidade PDV-BE-04.

TenantResolver humano não mudou. Tenant SUSPENDED humano permanece finding.
PENDING → ACTIVE aqui indica provisionamento de identidade, sem emissão de token
e sem habilitar requisições operacionais. O primeiro UUID não nulo persistido
é imutável no model, inclusive quando fixtures/fluxos internos o informam antes;
reinstalação e reatribuição exigirão procedimento futuro explícito.

### Auditoria, logging e evidências

Serviços não escrevem código/hash/UUID em logger ou AuditLog. O helper AuditLog
atual deriva contexto humano/HTTP e gera request_id quando ausente; não foi
acoplado ao domínio nem fabricado request_id. Histórico mantém emissão, prazo,
contador, invalidação e consumo. Auditoria contextual de operador e tentativas
HTTP será definida na camada chamadora, sem segredos.

Tests-first: 48 erros por classes ausentes. Resultado final: emissão 9 PASS/33
assertions; consumo 10 PASS/34; segurança/replay 29 PASS/91; transação 3 PASS/12.
Suíte completa: 1302 testes, 1300 PASS, 0 FAIL/ERROR, 2 RISKY conhecidos,
0 SKIPPED, 5060 assertions. Todas as regressões SEC/COR/PDV-BE-01/02 verdes;
PHPStan idêntico ao baseline de 17 achados.

MySQL descartável validou schema, FKs, RESTRICT, unique, datas/NULL, attempts,
expiração e rollback/reaplicação. Processos PHP independentes, sincronizados por
barreiras e lock mantido pelo processo coordenador, provaram: mesmo código tem
um sucesso/um replay; mesmo UUID entre tenants tem um provisionamento; emissão
concorrente deixa um código válido. SQLite :memory: prova comportamento funcional,
sem prometer concorrência multiprocess. Isso não é teste de carga distribuída.
Migration 2026_10_08_130000 aplicada em staging após DDL/backup; 42 executadas,
0 pendentes; schema e smoke validados, sem dados reais ou erros operacionais novos.

### Fronteira com PDV-BE-04 e PDV-BE-05

Domínio de pairing está concluído. Nenhum endpoint foi criado: POST pair,
GET health e GET terminal permanecem no PDV-BE-05. Emissão da credencial é
exclusivamente PDV-BE-04; o fluxo HTTP final com credencial depende das duas
etapas. Perda de resposta não reabre código consumido: recuperação segura deve
ser desenhada antes do contrato final, sem transformar replay em sucesso.

Backend continua **NÃO APTO PARA PDV** e Java Fase 4 aguarda contratos reais.
F2.6 segue liberada e não iniciada. PDV-BE-04 não foi iniciado nesta rodada.

## Machine credential e Sanctum — implementado no PDV-BE-04

**A ordem de implementação foi de segurança.** O callback do Sanctum terminava em
`: true`: qualquer tokenable que não fosse `User` autenticava apenas porque o
guard havia considerado o token válido, sem checagem de estado. O guard não
fecha essa porta — `auth.guards.sanctum.provider` é nulo neste projeto, então o
`hasValidProvider()` do Sanctum aceita qualquer tokenable —, o que faz do
callback o único ponto de controle. Portanto o fail-closed foi a primeira
alteração e foi coberto por teste **antes** de o Terminal receber `HasApiTokens`.
Entre uma coisa e outra o Terminal caía em `return false`: nunca existiu janela
em que um Terminal tokenable fosse aceito pelo ramo permissivo.

**Política de sujeito, por tipo e fail-closed:**

| Sujeito | Decisão |
|---|---|
| `User` | `User::isActive()` — SEC-02 preservado integralmente |
| `Terminal` | `TerminalAuthenticationEligibility` |
| qualquer outro, inclusive tokenable órfão | `false` |

O callback só restringe: `$valido = false` vindo do guard nunca é revertido.

**Terminal é sujeito autenticável.** Implementa
`Illuminate\Contracts\Auth\Authenticatable` e usa `HasApiTokens`. O contrato é
exigência técnica verificada no Sanctum 4.3.3 instalado, não precaução: o guard
devolve o tokenable como `$request->user()` e `GuardHelpers::setUser()` declara o
tipo — é por ele que `Sanctum::actingAs()` passa. O trait sozinho fornece
`tokens()`/`createToken()`/`tokenCan()`, não identidade. Não há password,
remember_token, e-mail, login, auth provider próprio nem password broker:
`getAuthPassword()` e `getAuthPasswordName()` lançam `LogicException` em vez de
devolver string vazia, e `getRememberTokenName()` devolve null, que é como o
framework reconhece a ausência de "lembrar-me". Terminal não é model de nenhum
provider configurado.

**Ability.** `MachineTokenAbility::TERMINAL_READ` = `pdv:terminal:read`, em fonte
separada do `TokenAbility` humano, que já reservava essa separação em docblock.
Nunca `*`, nunca `business:read`/`business:write`. Abilities de venda,
sincronização, estoque ou pagamento não foram antecipadas: entram quando as APIs
correspondentes existirem.

**Expiração: o teto global foi verificado no Guard instalado, não presumido.**
`Guard::isValidAccessToken()` combina as duas expirações com E lógico:

```
(! $this->expiration || $accessToken->created_at->gt(now()->subMinutes($this->expiration)))
&& (! $accessToken->expires_at || ! $accessToken->expires_at->isPast())
&& $this->hasValidProvider($accessToken->tokenable)
```

O `expiration` global (`SANCTUM_EXPIRATION_MINUTES`, 720) é medido sobre
`created_at` e vale para **qualquer** tokenable, inclusive os polimórficos: é
teto absoluto, não default. Um `expires_at` de máquina acima de 720 minutos
seria ficção — o token morreria no teto de todo jeito. Por isso
`pdv.machine_credentials.ttl_minutes` vale 720, mantendo o que o projeto promete
igual ao que o Sanctum cumpre, e há teste provando que um token com `expires_at`
de um ano ainda é recusado após o teto.

`SANCTUM_EXPIRATION_MINUTES` **não foi alterado** e nenhum token humano foi
revogado. A auditoria do banco antes da implementação encontrou 0
`personal_access_tokens` (0 com `expires_at`, 0 sem), logo não havia política a
migrar. Validade de máquina maior exigiria remover o teto global — o que
transformaria todo token humano sem `expires_at` em token sem prazo — ou dar à
máquina um guard próprio com expiração independente. Nenhuma das duas foi feita:
é mudança de política de sessão humana e fica fora desta etapa.

**Um token ativo por Terminal, rotação e revogação.** Um Terminal operacional é
uma instalação física, e o `installation_id` é único e imutável desde o
PDV-BE-03; logo há no máximo uma credencial vigente, e reemitir é rotação.
`IssueTerminalMachineCredential` revoga antes de criar — nessa ordem, para que
uma falha deixe o Terminal sem credencial em vez de com duas.
`RevokeTerminalMachineCredentials` é o ponto único de deleção, restrito pela
morphMany do tokenable (`tokenable_type` + `tokenable_id`); nunca por nome,
ability ou tenant_id, que não distinguem sujeito. Testes provam que token humano
e token de outro Terminal não são alcançados.

**Decisão revista em relação à tabela de status do PDV-BE-02: sair de `ACTIVE`
revoga as credenciais.** A tabela dizia, para `BLOCKED`, "tokens permanecem
sujeitos ao status". O PDV-BE-04 é mais estrito: um gancho Eloquent `updated`
revoga fisicamente as credenciais quando o status deixa de ser `ACTIVE`, tanto em
`BLOCKED` quanto em `REVOKED`. A negação por requisição continua sendo a garantia
principal — Terminal bloqueado não autentica nem com token íntegro —, e a remoção
resolve o passo seguinte: desbloquear não ressuscita uma credencial que passou
tempo fora de controle. Voltar a `ACTIVE` exige nova emissão. Para `REVOKED` a
remoção física é requisito, e a irreversibilidade do PDV-BE-02 segue valendo —
nem reativação, nem nova credencial. Limite conhecido, igual ao do validador de
vínculos: o gancho é Eloquent; SQL direto, bulk update e `saveQuietly` mudam
status sem passar por ele e não são fluxo oficial de administração.

**Texto puro.** Entregue uma única vez, em `IssuedTerminalMachineCredential` ou
`TerminalProvisioningResult`. Nunca persistido — o Sanctum guarda só o SHA-256 —,
nunca logado, nunca auditado, nunca em mensagem de exceção. `__debugInfo()`
censura o valor nos dois objetos, porque é por `var_dump`/`dd`/log de objeto que
um segredo acaba em arquivo. Não há coluna no projeto que receba o valor legível,
nem forma de relê-lo depois.

**Sem refresh token.** Auditado e dispensado: a política é credencial direta com
rotação. Um segundo segredo de longa duração só ampliaria a superfície sem
resolver nada que a rotação não resolva.

## Fronteira humana e estados operacionais — implementado no PDV-BE-04

User humano → `TenantResolver` atual, **não alterado nesta etapa**:
`$request->user()`, tipo User modular, conta/vínculo ativos, header/sessão/vínculo
único e limpeza do contexto ao recusar. Sujeito não User continua recusado, e é
essa recusa por tipo que forma metade da fronteira bidirecional.

Terminal → caminho próprio. `TerminalContext` é singleton por requisição, no
padrão do `TenantContext`, e expõe terminal, tenant, company e branch. O
middleware `terminal.context` (`ResolveTerminalContext`) exige sujeito
`Terminal`, recusa `X-Tenant-ID` e preenche `TerminalContext` e `TenantContext` a
partir do Terminal autenticado — o `TenantContext` porque o `TenantScope` dos
models de negócio o consulta, e é isso que permite a uma rota de máquina
consultar dados com o mesmo filtro do caminho humano. Falha limpa os dois
contextos, pelo mesmo motivo do `recusar()` do `TenantResolver`.

`X-Tenant-ID` é **recusado para máquina, inclusive quando correto** (403,
seguindo a convenção do `ResolveTenantMiddleware`). Aceitá-lo "porque coincide"
ensinaria o cliente de PDV a enviar o cabeçalho, e a divergência entre o que ele
manda e o vínculo real passaria a ser decisão de servidor. O Terminal autenticado
é a única autoridade sobre o contexto.

**Fronteira bidirecional, por tipo.** Rota de máquina recusa `User`; rota humana
recusa `Terminal`. Como ability não prova tipo de sujeito, os dois casos são
testados também com a ability "certa" adulterada na fixture: `User` com
`pdv:terminal:read` segue recusado em rota de máquina, e Terminal com
`business:read`/`business:write` segue recusado em `/api/v1/products`, onde chega
ao `TenantResolver` e é barrado por tipo. A ability de máquina tem middleware
próprio, `machine.ability:<ability>` (`EnsureMachineTokenAbility`), e não o
`EnsureTokenAbility` humano, que deriva a ability do método HTTP — mapeamento
que é regra da sessão humana e devolveria a máquina ao espaço de abilities das
pessoas.

**Pipeline, documentado e ainda sem rota registrada:**

```
auth:sanctum → terminal.context → machine.ability:<ability> → controller PDV
```

**Estados operacionais aplicados por requisição.**
`TerminalAuthenticationEligibility` exige:

| Entidade | Exigência |
|---|---|
| Terminal | `ACTIVE` e `installation_id` não nulo |
| Tenant | `Tenant::isActive()` — `active=true` e status `ACTIVE`/`TRIAL`; soft delete recusa |
| Company | `ACTIVE` |
| Branch | `ACTIVE` |
| Vínculos | company/branch/tenant coerentes entre si |

`TRIAL` continua aceito, como no pairing: a semântica vem de `Tenant::isActive()`,
compartilhada com o fluxo humano, e mudá-la só para máquina criaria duas
definições de "tenant em operação". A coerência de vínculos é reconferida porque
as FKs são individuais e não existe FK composta; quem lê o Terminal para
autenticar não passa pelo validador de escrita.

A regra de "estrutura operacional" foi extraída para `TerminalStructure` e é
compartilhada com `PairingEligibility`. Só a forma de carregar difere: o pairing
lê os três pais com `lockForUpdate`, por decidir uma transição, e a autenticação
lê sem lock a cada requisição. A coerência de vínculos ficou em método separado
de propósito — juntá-la ao estado trocaria o motivo de recusa que o pairing já
devolvia (`invalid-assignment` viraria `structure-unavailable`).

A verificação é **por requisição**, e não só na emissão: suspender Tenant,
Company ou Branch, ou bloquear o Terminal, derruba um token já emitido sem que
nada o toque. Há teste que altera o status fora do Eloquent — caso em que o
gancho de revogação não roda e a linha do token permanece no banco — provando
que a negação vem do estado, não da ausência da linha.

Hoje o acesso humano verifica conta e membership, sem exigir `Tenant::isActive()`;
um Tenant SUSPENDED pode ser resolvido. Esse finding continua aberto e o
User/TenantResolver não foi alterado. A lacuna não foi copiada para a
autenticação de máquina, e corrigir máquinas não mudou o acesso humano.

**`installation_id` não autentica.** Continua identificador público da instalação,
não segredo nem prova de posse: não é exigido por requisição e nada nele
autoriza. Quem prova posse é a credencial. Device fingerprinting e detecção de
clonagem não foram implementados; se vierem, serão camada adicional.
Recuperação de credencial perdida segue sendo questão operacional futura — novo
processo administrativo ou re-pairing —, sem fluxo automático nesta etapa.

## Contratos HTTP implementados no PDV-BE-05

Três endpoints, nada além. A camada é fina de propósito: HTTP → validação
estrutural → serviço existente → resposta pública. Nenhuma regra de pareamento,
credencial ou elegibilidade foi reimplementada aqui.

Módulo `app/Modules/Pdv`, com `Routes/api.php` exigido por `routes/api.php`, no
mesmo padrão dos outros cinco módulos. Rotas de API: 49 → 52.

### GET /api/v1/pdv/health

| | |
|---|---|
| **Auth** | nenhuma; público |
| **Middleware** | grupo `api` (correlação + 401 JSON) |
| **Request** | nenhum parâmetro |
| **Response** | `{"data":{"status":"ok","api":"pdv","version":"v1"}}` |
| **Status** | 200 |
| **Erros** | — |
| **Rate limit** | nenhum |
| **X-Request-ID** | gerado se ausente, preservado se válido |

É contrato de CLIENTE, não diagnóstico interno. Responde a única pergunta que o
aplicativo de loja precisa fazer: "alcancei o backend certo, no contrato certo?".
O `/api/health` segue existindo, intacto, com estado de banco e cache para quem
opera a plataforma — e este **não é alias** dele. Ambiente, versões de
PHP/Laravel, host, container, caminho, commit, timestamp e estado de dependências
não aparecem: não têm consumidor aqui e cada um ajudaria quem mapeia a
infraestrutura. Há teste asseverando a ausência de cada termo.

### POST /api/v1/pdv/terminals/pair

| | |
|---|---|
| **Auth** | nenhuma; público por necessidade — antes do pareamento o Terminal não tem credencial |
| **Middleware** | `throttle:pdv-pairing` |
| **Request** | `pairing_code` (string, tamanho exato) e `installation_id` (`uuid:4`) |
| **Response** | `data.terminal{id,name,status,installation_id}`, `data.tenant{id,name}`, `data.company{id,trade_name}`, `data.branch{id,name,code}`, `data.credential{token_type,access_token,expires_at}` |
| **Status** | 200 |
| **Erros** | 422 `validation_error`, 422 `pairing_failed`, 429 `rate_limited`, 500 `internal_error` |
| **Rate limit** | IP 20/10min e selector 5/10min |
| **Cache** | `Cache-Control: no-store, private` |

200 e não 201: o Terminal já existia, pré-cadastrado pela administração; o que a
chamada cria é o vínculo da instalação e a credencial. Chama
`ConsumeTerminalPairingCode` diretamente — hash, selector, prazo, attempts, lock,
transição de status e emissão continuam decididos lá, em uma única transação.

**A credencial aparece SOMENTE nesta resposta, e somente no sucesso.** O banco
guarda apenas o SHA-256 do Sanctum; não existe coluna com o valor legível nem
endpoint que o devolva. Perder esta resposta significa precisar de um novo
pareamento — o código é de uso único e o `installation_id` é imutável. É por isso
que a resposta vai com `no-store, private`: um proxy no caminho da loja poderia
guardar e reentregar a credencial de um Terminal para outro.

A validação estrutural é deliberadamente pobre e **nunca mais permissiva que o
domínio**: `installation_id` usa `uuid:4` com versão explícita, porque a regra
`uuid` sem parâmetro aceita qualquer versão e seria mais frouxa que o
`InstallationId::normalize()`. Mensagens não ecoam o valor enviado.

### GET /api/v1/pdv/terminal

| | |
|---|---|
| **Auth** | machine credential (Bearer) |
| **Middleware** | `auth:sanctum` → `terminal.context` → `machine.ability:pdv:terminal:read` |
| **Request** | nenhum parâmetro — não existe "qual terminal?" a responder |
| **Response** | mesmas quatro entidades do pareamento, mais `data.credential{expires_at}` |
| **Status** | 200 |
| **Erros** | 401 `unauthenticated`, 403 `forbidden`, 500 `internal_error` |
| **Rate limit** | nenhum |

Tudo vem do `TerminalContext`, preenchido pelo middleware a partir do Terminal
autenticado. Um Terminal só pode consultar a si mesmo, e isso é consequência da
forma do endpoint — sem identificador em rota, corpo ou cabeçalho —, não de uma
checagem que alguém poderia esquecer.

**Não devolve credencial.** Apenas `credential.expires_at`, de
`currentAccessToken()`, sem token nem hash. Foi incluído com consumidor concreto:
o PDV opera o dia inteiro, a única recuperação hoje é novo pareamento presencial,
e saber a expiração permite avisar o operador antes do turno virar em vez de
descobrir com um 401 no meio de uma venda.

### Mapeamento manual do contrato

Nenhum model é serializado. `return $terminal` ou `toArray()` faria qualquer
coluna, cast ou relação futura entrar no contrato sem decisão — é assim que
`document`, `legal_name` ou um atributo interno viram resposta pública por
acidente. `PdvTerminalPayload` lista o que é público, campo por campo, e só com
coluna que existe: `Company` **não tem** `name`, tem `trade_name`. Testes fecham
a estrutura com `array_keys`, então acrescentar campo exige editar o arquivo e o
teste.

`legal_name` e `document` ficaram fora: dado cadastral/fiscal entra junto dos
contratos fiscais. Configuração operacional — timezone, moeda, parâmetros de
caixa — **não foi inventada**: não há consumidor ainda, e campo sem consumidor é
contrato que se paga para manter. Será definida junto dos contratos funcionais.

Nunca retornados: PAT, hash de token, histórico/selector/hash de pairing,
permissões humanas, vínculos de usuário, dados administrativos.

## Contrato público de erro do PDV

Escopado em `/api/v1/pdv/*`. Forma fixa:

```json
{"error": {"code": "...", "message": "...", "request_id": "..."}}
```

| `code` | HTTP | Quando | Detalha campo? |
|---|---|---|---|
| `validation_error` | 422 | requisição malformada | sim, em `error.errors` |
| `pairing_failed` | 422 | qualquer falha de pareamento | não |
| `unauthenticated` | 401 | credencial ausente, inválida, expirada, rotacionada, ou sujeito/estrutura fora de operação | não |
| `forbidden` | 403 | autenticado sem autorização de rota, contexto ou ability | não |
| `rate_limited` | 429 | freio do pareamento; acompanha `Retry-After` | não |
| `internal_error` | 500 | erro inesperado | não |

`PdvErrorResponse` é a fonte única: middlewares de máquina e renderizadores de
exceção passam por ela. `error.request_id` é exatamente o valor do cabeçalho
`X-Request-ID`, lido do Request já normalizado pelo `RequestCorrelationMiddleware`
— sem duplicar aquela implementação. Vai no corpo além do cabeçalho porque quem
atende a loja costuma ter a tela, não o header.

**Falha de pareamento não vira oráculo.** O domínio distingue selector
inexistente, segredo errado, expirado, consumido, invalidado, Terminal inadequado
e estrutura fora de operação; todos saem como o mesmo 422 com a mesma mensagem.
Saber que o selector existe mas o segredo está errado diria ao atacante que ele
acertou metade de um código de 18 caracteres. O mesmo vale para o 401: nada
revela se foi o Terminal, o Tenant, a Company ou a Branch que saiu de operação.

**Escopo preservado.** Cada renderizador devolve null fora do prefixo PDV, então
as 49 rotas anteriores mantêm o contrato que tinham — há teste provando que
`/api/v1/products` sem token segue respondendo `{"message":"Não autenticado"}` e
que o login humano mantém o formato antigo de validação.

Duas famílias ficam fora do catch-all de 500, e as duas por necessidade:
`HttpExceptionInterface` são status deliberados (404, 405), e
`HttpResponseException` **não é erro** — é o mecanismo pelo qual o Laravel carrega
uma resposta já montada através da pilha, e é como o `throttle` entrega o 429 do
nosso próprio limiter. Como o `renderViaCallbacks()` roda antes do `match` que
desembrulha essa exceção, um catch-all sem essa guarda transformaria o 429 em
500 — foi exatamente o que os testes de rate limit apontaram.

## Freio do pareamento

Limiter nomeado `pdv-pairing`, sem reaproveitar o `api-login` humano.

| Dimensão | Limite | Chave |
|---|---|---|
| IP | 20 / 10 min | `pdv-pair:ip:<ip>` |
| Selector | 5 / 10 min | `pdv-pair:selector:<sha256 do selector>` |

As janelas acompanham o domínio: o código vive 10 minutos, então contar em 10
minutos é contar a vida útil do alvo. O teto por selector é igual ao
`max_attempts` do PDV-BE-03, para o freio de HTTP não ser mais frouxo que o do
banco; o teto por IP é mais alto porque uma loja instala vários caixas atrás de
uma única saída de rede — mesma lógica do `api-login`.

**Os três controles são complementares e nenhum basta sozinho:** os `attempts`
persistentes travam o ataque a UM código mas não impedem varrer muitos; o freio
por IP trava o volume de uma origem mas não um ataque distribuído; o freio por
selector trava a insistência contra um alvo vindo de muitos IPs, e trocar de
selector devolve o atacante ao freio por IP.

Só a parte **pública** do código entra na chave, via
`PairingCode::selectorFrom()`, que extrai o selector e nunca devolve nem registra
o segredo. Código sem selector plausível fica apenas sob o freio por IP, em vez
de virar chave de cache com segredo dentro. O 429 não informa se o selector
existe, se há Terminal por trás, nem quantas tentativas restam no banco. Teste
prova que a tentativa recusada por volume **não chega ao serviço**: o `attempts`
no banco não se move.

### IP real atrás do proxy

Verificado contra a stack antes de escrever o limiter, não presumido. Cadeia:
Internet → nginx do host (TLS) → `127.0.0.1:9010` → nginx do container →
php-fpm. O `REMOTE_ADDR` que chega ao PHP é `172.21.0.1`, gateway da rede do
compose, dentro de `172.16.0.0/12` e portanto confiado pelo `trustProxies`
existente. O vhost do host define `X-Forwarded-For $proxy_add_x_forwarded_for`,
que **acrescenta à direita** o endereço real.

| Caso | `$request->ip()` |
|---|---|
| cliente público `198.51.100.42` | `198.51.100.42` |
| cliente injeta `203.0.113.7` à esquerda do XFF | `198.51.100.42` — spoof ignorado |
| sem XFF (acesso direto ao container) | `172.21.0.1` |
| `REMOTE_ADDR` não confiado | o próprio; XFF descartado |

O Symfony lê a entrada mais à direita que não seja proxy confiado, que é
exatamente a que o nginx do host acrescentou. **Rate limiting por IP é confiável
para cliente da Internet**, e nenhuma alteração em `TrustProxies` foi necessária.
Limite conhecido: um cliente que já venha de faixa privada confiada poderia
injetar a entrada à esquerda e ser lido por ela — inerente ao modelo de proxy
confiado e não alcançável da Internet, já que o nginx do container escuta apenas
em `127.0.0.1:9010`. Os testes simulam cliente por `REMOTE_ADDR`, que é o
equivalente fiel: endereço público não é proxy confiado, o XFF é ignorado e a
autoridade sobre o IP é a mesma de produção.

## Questões abertas e sequência

- PDV-BE-02: concluído — Terminal/vínculos, validação na escrita, status,
  unicidade e Policy. PDV-BE-03 tornou a instalação persistida imutável;
  reatribuição e política de retenção continuam decisões futuras.
- PDV-BE-03: concluído — domínio de pairing, prazo, attempts persistentes,
  consumo atômico, replay e instalação. HTTP/rate limiting/recuperação do
  contrato final ainda futuros.
- PDV-BE-04: concluído — política de sujeito fail-closed, Terminal autenticável,
  credencial de máquina com ability própria, expiração no teto real do Sanctum,
  um token por Terminal com rotação, revogação ao sair de `ACTIVE`, enforcement
  por requisição, contexto de máquina e emissão integrada ao pairing. Ficam
  abertos: validade acima de 720 minutos (exigiria guard próprio ou remoção do
  teto global), recuperação de credencial perdida, reatribuição de instalação e
  detecção de clonagem.
- PDV-BE-05: concluído — os três contratos HTTP, freio do pareamento em duas
  dimensões, contrato público de erro escopado, 82 testes pelas rotas reais e
  validação ponta a ponta em staging. **Marco de aptidão para a integração
  inicial do PDV atingido.** Ficam abertos: recuperação/rotação de credencial por
  HTTP, configuração operacional no contrato (timezone, moeda, parâmetros de
  caixa), versionamento do contrato além de `v1` e os contratos funcionais de
  operação comercial.
- Próximo: **LucraOne PDV Java — Fase 4**, contrato e conectividade com a API.

Findings fora do escopo preservados: PERF-01; validação UUID em category_ids ULID;
User legado aparentemente órfão; senha default de desenvolvimento no exemplo;
Tenant SUSPENDED humano; construtor legado do StructuredLoggingService; FKs
individuais sem garantia composta no banco; limite de SQL direto/`saveQuietly`
fora das garantias Eloquent. Nenhuma dessas correções nem o desenvolvimento
Java/F2.6 foi iniciado nesta rodada.

**Validação do PDV-BE-05.** 82 testes novos pelas rotas reais (health 6,
pareamento 25, rate limit 7, Terminal 28, contrato de erro 12, ponta a ponta 4),
1120 assertions. Suíte completa: 1458 total, 1456 PASS, 0 FAIL/ERROR, 2 RISKY
preexistentes, 6368 assertions. PHPStan 17, idêntico ao baseline. Nenhuma
migration; schema de `personal_access_tokens` intocado; rotas 49 → 52. Staging
ponta a ponta por HTTPS com fixture sintética `PDV-BE-05-SMOKE-<ULID>`, criada
após confirmar que nenhum listener, observer, e-mail, notificação ou regra de
automação é disparado, e removida integralmente em ordem segura de FK — totais de
volta ao pré-smoke e nenhuma credencial temporária ativa. Backup
`/var/backups/lucraone/pdv-be-05-before-smoke-20261010-153355.sql`, 143080 bytes,
root/600.

**Validação do PDV-BE-04.** 74 testes novos (política de sujeito 7, Terminal
autenticável/enforcement 20, credencial 11, ciclo de vida 12, contexto/fronteira
15, pairing+credential 9); Terminals completo 125 PASS. Suíte completa: 1376
total, 1374 PASS, 0 FAIL/ERROR, 2 RISKY preexistentes, 5248 assertions. PHPStan
17 achados, idêntico ao baseline. Nenhuma migration e nenhuma alteração no schema
de `personal_access_tokens`. Rotas de API seguem 49, nenhuma de PDV.
