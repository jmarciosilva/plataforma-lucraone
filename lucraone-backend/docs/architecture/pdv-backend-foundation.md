# PDV-BE — Fundação do backend e entidade Terminal

**Trilha:** PDV-BE · **Data:** 2026-10-08
**Estado:** PDV-BE-01 e PDV-BE-02 implementados; pairing, machine auth e contratos PDV ainda não implementados.
**Commit funcional PDV-BE-01:** `2d203951b2e824234b53b0e675ffe8e5b82bf5c3`.
**Commit funcional PDV-BE-02:** `1e96b207a102d81ed089ab1f7693ef2cc6aadb89`.

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
Branch API, pairing, machine authentication e rotas PDV continuam ausentes.
As seções de pairing, machine auth e endpoints continuam propostas futuras.

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
A imutabilidade pós-pairing continua pendente: ainda não existe pairing.

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

PDV-BE-02 não torna o backend apto para PDV. Pairing, credenciais e endpoints
abaixo continuam propostas futuras. F2.6 permanece liberada e não iniciada.

## Pairing futuro

1. Administrador autorizado pré-cadastra Terminal e vínculos no backend.
2. Backend emite código aleatório de pareamento, de uso único e curta duração.
3. PDV envia `pairing_code` e `installation_id` por HTTPS.
4. Backend valida código, prazo, tentativas, Terminal e coerência dos vínculos.
5. Consumo atômico do código, vínculo da instalação e emissão da credencial.
6. Resposta: `terminal_id`, `tenant_id`, `company_id`, `branch_id` e machine
   credential, entregue somente na emissão. O código fica consumido.

Proposta: armazenar hash do código, nunca o valor em claro ou em logs; usar
entropia adequada e comparação segura. Prazo inicial sugerido de 10 minutos,
a confirmar em PDV-BE-03. Limitar tentativas por IP e código/Terminal, sem
armazenar código bruto nas chaves de rate limit; definir limites numéricos após
análise de uso e abuso. Respostas inválidas não devem revelar Terminal/vínculos.

Replay e concorrência devem ser recusados por consumo transacional/atômico;
apenas uma tentativa pode emitir a credencial. Rotação invalida código anterior.
Uma perda de resposta após consumo não permite reutilizar o código: recuperação
administrativa ou protocolo seguro de recuperação deve ser definido. O endpoint
terá correlation ID; código e token não podem aparecer em logs ou auditoria.

**Dependência real:** PDV-BE-03 prepara pairing/provisionamento; o fluxo que
retorna credencial só fica completo após PDV-BE-04. Nenhum pairing funcional
deve ser anunciado antes dessa dependência estar satisfeita.

## Machine credential e Sanctum

Requisitos: credencial específica do Terminal, revogável, com expiração explícita,
armazenada como hash no backend; sem User/password/login humano. Tenant,
Company e Branch derivam da identidade autenticada, nunca de `X-Tenant-ID`.
Sem `*` nem herança automática de `business:read`/`business:write`.

**Sanctum é tecnicamente viável**, conforme o código instalado: PersonalAccessToken
tem relação polimórfica `tokenable`, e `tokenable_id` já suporta ULID. O tokenable
proposto seria o futuro Terminal. `HasApiTokens` pode ser reutilizado, desde que
Terminal cumpra o contrato de autenticação necessário ao guard, além de usar o
trait. O trait sozinho não estabelece uma identidade autenticável.

O callback atual do Sanctum preserva a validade calculada pelo guard e exige
atividade quando o dono é User; para outros tipos retorna true se o guard já
considerou o token válido. Portanto não é exclusivo de User, mas **não aplica**
status de Terminal/Tenant/Company/Branch. PDV-BE-04 deverá definir essa política
explicitamente, preservando SEC-02. Rotas humanas e de máquina precisam recusar
o tipo incorreto de sujeito; abilities isoladas não substituem essa fronteira.

Proposta mínima de ability: `pdv:terminal:read` para consultar o próprio Terminal.
Demais operações receberão abilities específicas quando seus contratos existirem.
Esses nomes são proposta documental; TokenAbility humano não mudou.

Expiração específica, rotação, renovação e número de tokens por Terminal ainda
precisam ser definidos. O limite global atual do Sanctum é 720 minutos e se
aplica também aos tokens polimórficos; um `expires_at` futuro não pode ser
tratado como extensão automática desse limite. Não aumentar a expiração humana
silenciosamente para acomodar máquinas. Avaliar política diferenciada/guard ou
outra estratégia antes de fechar a implementação em PDV-BE-04.

Revogação pode excluir todos os tokens ligados ao tokenable Terminal, além de
verificar seu status a cada request. Revogar apenas um token é possível na
rotação; bloquear operação não depende somente de apagar um token isolado.

## Fronteira humana e estados operacionais

User humano → TenantResolver atual, preservado integralmente nesta rodada:
`$request->user()`, tipo User modular, conta/vínculo ativos, header/sessão/vínculo
único e limpeza do contexto ao recusar. Sujeito não User é recusado.

Terminal/machine client → estratégia própria, explícita, que deriva contexto dos
vínculos do Terminal autenticado e aplica suas invariantes. Não usar o resolver
humano nem permitir que `X-Tenant-ID` escolha outro estabelecimento.

**Decisão pendente para PDV-BE-04:** machine client não deverá operar com Tenant
`SUSPENDED`/`CANCELLED`, Company `INACTIVE`/`SUSPENDED` ou Branch
`INACTIVE`/`SUSPENDED`. Propõe-se Tenant `active=true` e status `ACTIVE`/`TRIAL`,
Company/Branch `ACTIVE` e Terminal `ACTIVE`; entidades ausentes/arquivadas devem
recusar acesso. A aceitação de TRIAL e a recuperação após suspensão exigem
confirmação de produto. Testes deverão provar esses bloqueios.

Hoje o acesso humano verifica conta e membership, sem exigir `Tenant::isActive()`;
um Tenant SUSPENDED pode ser resolvido. Esse finding continua aberto, sem alteração
do User/TenantResolver nesta etapa. Não copiar essa lacuna para a autenticação
de máquina nem presumir que corrigir máquinas mudará o acesso humano.

## Endpoints futuros: contratos conceituais

| Endpoint | Acesso/credencial | Finalidade e resposta conceitual | Origem do tenant |
|---|---|---|---|
| `GET /api/v1/pdv/health` | Público, sem credencial | Conectividade, versão do contrato e estado público do backend | Nenhum contexto de tenant |
| `POST /api/v1/pdv/terminals/pair` | Código + installation_id; ainda sem machine credential | Consumir pairing e entregar identidade/vínculos e credencial na emissão | Terminal pré-cadastrado associado ao código |
| `GET /api/v1/pdv/terminal` | Machine credential, sujeito Terminal e ability específica | Próprio Terminal, Tenant, Company, Branch, status e configuração mínima | Terminal autenticado |

Todos terão `X-Request-ID`. Health PDV não expõe DB, Redis, detalhes internos ou
segredos; não é alias da resposta detalhada de `/api/health`. Pairing exige rate
limit, prazo, consumo único e proteção de replay descritos acima. Consulta de
Terminal nunca retorna tokens, hashes ou pairing codes. Forma exata do JSON,
versionamento e códigos de erro serão fechados antes da integração Java real.
**Nenhum destes endpoints existe nesta entrega.**

## Questões abertas e sequência

- PDV-BE-02: concluído — Terminal/vínculos, validação na escrita, status,
  unicidade e Policy. Reatribuição/imutabilidade pós-pairing e retenção de
  histórico continuam decisões futuras.
- PDV-BE-03: próximo, ainda planejado — pairing, consumo concorrente, rate
  limits, expiração e recuperação.
- PDV-BE-04: escolha final da credencial, validade/renovação, revogação, sujeito,
  abilities e aplicação dos estados operacionais. Completa emissão pelo pairing.
- PDV-BE-05: endpoints reais, contrato Java, testes HTTP e staging; só então
  avaliar o marco **backend apto para PDV**.

Findings fora do escopo preservados: PERF-01; validação UUID em category_ids ULID;
User legado aparentemente órfão; senha default de desenvolvimento no exemplo;
Tenant SUSPENDED humano; construtor legado do StructuredLoggingService. Nenhuma
dessas correções nem o desenvolvimento Java/F2.6 foi iniciado nesta rodada.
