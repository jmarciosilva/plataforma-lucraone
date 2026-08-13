# Prompt para Claude CLI — Início do Projeto LUCRAONE

Você será responsável por **iniciar e conduzir o desenvolvimento do projeto LUCRAONE**, uma plataforma SaaS inteligente de automação comercial.

O projeto deverá ser criado e desenvolvido exclusivamente dentro da pasta:

```text
D:\PROJETO-LUCRAONE
```

Você possui **autonomia total de leitura, criação, alteração, movimentação e exclusão de arquivos exclusivamente dentro dessa pasta**, podendo executar comandos, instalar dependências do projeto, criar migrations, classes, testes, configurações, documentação, scripts, Docker, arquivos auxiliares e demais recursos necessários para cumprir o roadmap.

**Não altere arquivos, configurações ou projetos fora de `D:\PROJETO-LUCRAONE` sem necessidade explícita.**

---

# 1. DOCUMENTOS OFICIAIS DO PROJETO

Na raiz de:

```text
D:\PROJETO-LUCRAONE
```

existem três documentos que constituem a **fonte oficial de verdade para o desenvolvimento**.

Você deverá localizá-los, lê-los integralmente antes de escrever código e utilizá-los como referência permanente durante todo o projeto.

Os documentos são:

```text
Projeto — Plataforma SaaS Inteligente de Automação Comercial.md

Roadmap Macro de Desenvolvimento — Plataforma SaaS Inteligente de Automação Comercial.md

ROADMAP_FASE_01_FOUNDATION.md — Fundação Técnica da Plataforma.md
```

O primeiro documento define a visão geral, incluindo a arquitetura baseada em **PHP/Laravel + MySQL + Redis no SaaS e .NET/C# + SQLite no futuro PDV**, além do modelo SaaS Multi-Tenant e Offline-First.

O roadmap macro define a sequência de construção e estabelece que cada domínio deverá posteriormente possuir roadmap próprio contendo escopo, requisitos, arquitetura, banco, APIs, segurança, auditoria, testes e critérios de aceite. 
O roadmap detalhado da Fundação estabelece que esta é a primeira fase efetiva e que deverá priorizar **consistência arquitetural, segurança, isolamento multi-tenant, testes, auditoria e capacidade de evolução**.

---

# 2. REGRA PRINCIPAL

**Não improvise um projeto diferente daquele descrito nos documentos.**

Antes de implementar qualquer funcionalidade:

1. leia os três documentos;
2. compreenda a arquitetura;
3. identifique a fase atual;
4. identifique a etapa atual;
5. verifique as dependências;
6. implemente somente aquilo que pertence à etapa;
7. execute os testes;
8. audite o resultado;
9. atualize o status da documentação;
10. somente depois avance.

Não pule fases para criar funcionalidades visualmente interessantes.

Nesta etapa inicial, por exemplo, produtos, estoque, vendas, fiscal, PDV e Customer Intelligence estão explicitamente fora do escopo da Fundação.

---

# 3. FASE ATUAL

O desenvolvimento deverá começar pela:

```text
FASE 01 — FOUNDATION
```

Documento principal:

```text
ROADMAP_FASE_01_FOUNDATION.md — Fundação Técnica da Plataforma.md
```

A Fundação deverá construir inicialmente:

```text
Setup do Projeto
Arquitetura
Ambientes
Banco de Dados
Redis
Multi-Tenancy
Empresas
Filiais
Usuários
Autenticação
Roles & Permissions
API
Auditoria
Logs
Segurança
Testes
CI
Documentação
```

Esses elementos estão explicitamente definidos como escopo da Fundação.

---

# 4. NÃO DESENVOLVA TUDO DE UMA VEZ

Execute o desenvolvimento de forma incremental.

Utilize como referência as sprints definidas no roadmap da Fundação.

A lógica deverá ser semelhante a:

```text
FASE 01 — FOUNDATION

Sprint F1.1
Bootstrap / Infraestrutura

        ↓

Sprint F1.2
Multi-Tenancy

        ↓

Sprint F1.3
Empresas e Filiais

        ↓

Sprint F1.4
Identity / Authentication

        ↓

Sprint F1.5
Authorization

        ↓

Sprint F1.6
Audit / Observability

        ↓

Sprint F1.7
Hardening / Auditoria Final
```

Cada sprint deverá possuir controle próprio de conclusão.

---

# 5. CONTROLE OBRIGATÓRIO DE STATUS

Quero poder abrir os arquivos Markdown a qualquer momento e saber exatamente:

- em qual fase estamos;
- qual sprint está sendo executada;
- quais etapas foram concluídas;
- quais estão em andamento;
- quais estão pendentes;
- quais estão bloqueadas;
- quais testes passaram;
- quais testes falharam;
- o que falta para concluir a fase.

Utilize estados padronizados.

```text
⬜ PENDING
🟡 IN_PROGRESS
✅ DONE
🔴 BLOCKED
⚠️ REVIEW_REQUIRED
```

Exemplo:

```markdown
## FASE 01 — FOUNDATION

Status: 🟡 IN_PROGRESS

### Sprint F1.1 — Bootstrap

Status: 🟡 IN_PROGRESS

- [x] Criar projeto Laravel
- [x] Configurar MySQL
- [x] Configurar Redis
- [ ] Configurar Docker
- [ ] Configurar CI
- [ ] Executar auditoria da Sprint

Conclusão: 3/6 — 50%
```

Quando todos os itens forem concluídos:

```markdown
### Sprint F1.1 — Bootstrap

Status: ✅ DONE
Conclusão: 6/6 — 100%
```

E somente quando todas as sprints da fase forem concluídas:

```markdown
# FASE 01 — FOUNDATION

Status: ✅ DONE
Conclusão: 100%
```

---

# 6. NUNCA MARQUE COMO CONCLUÍDO SEM TESTAR

Uma tarefa não está concluída apenas porque o código foi criado.

O fluxo obrigatório será:

```text
IMPLEMENTAÇÃO
      ↓
VALIDAÇÃO
      ↓
TESTES
      ↓
TRATAMENTO DE ERROS
      ↓
AUDITORIA
      ↓
DOCUMENTAÇÃO
      ↓
DONE
```

Só marque:

```text
✅ DONE
```

quando houver evidência objetiva de que a funcionalidade funciona.

---

# 7. REGRA CRÍTICA — TRANSAÇÕES DE BANCO DE DADOS

Esta regra é **obrigatória durante todo o projeto**.

Qualquer operação que modifique múltiplas entidades relacionadas e que precise manter consistência deverá utilizar transação de banco.

Exemplo conceitual:

```php
DB::beginTransaction();

try {

    // Executa todas as operações necessárias.

    DB::commit();

} catch (\Throwable $exception) {

    DB::rollBack();

    // Registra o erro adequadamente.

    throw $exception;
}
```

Entretanto, prefira quando adequado:

```php
DB::transaction(function () {

    // Operações atômicas.

});
```

desde que o tratamento de exceção permaneça explícito e adequado.

---

# 8. COMMIT E ROLLBACK SÃO OBRIGATÓRIOS EM OPERAÇÕES CRÍTICAS

Nunca permita o seguinte cenário:

```text
Criar Tenant
    ✅

Criar Empresa
    ✅

Criar Filial
    ❌

Criar Owner
    não executado
```

e deixar:

```text
Tenant
Empresa
```

gravados parcialmente.

O correto é:

```text
BEGIN TRANSACTION

Criar Tenant
Criar Empresa
Criar Filial
Criar Owner
Criar Roles
Criar Permissions

        ↓

Tudo funcionou?

SIM
 ↓
COMMIT

NÃO
 ↓
ROLLBACK
```

A própria Fundação prevê que provisionamentos como Tenant + Company + Owner devem ser tratados de maneira consistente e transacional.

---

# 9. TRATAMENTO DE EXCEÇÕES

**Nenhuma exceção importante deverá ser silenciosamente ignorada.**

Evite:

```php
try {

    // código

} catch (\Exception $e) {

}
```

Isso é proibido.

Toda exceção deverá possuir tratamento compatível com seu contexto.

Exemplo:

```php
try {

    // Operação.

} catch (DomainException $exception) {

    Log::warning(
        'Falha em regra de domínio.',
        [
            'exception' => $exception->getMessage(),
        ]
    );

    throw $exception;

} catch (\Throwable $exception) {

    Log::error(
        'Erro inesperado durante operação.',
        [
            'exception' => $exception,
        ]
    );

    throw $exception;
}
```

Não exponha:

- stack trace;
- credenciais;
- tokens;
- senhas;
- certificados;
- secrets;
- detalhes internos do banco;

para o usuário final.

---

# 10. TRATAMENTO GLOBAL DE ERROS

Implemente uma estratégia padronizada de erros da API.

Exemplo:

```json
{
    "error": {
        "code": "COMPANY_CREATION_FAILED",
        "message": "Não foi possível concluir a operação.",
        "request_id": "..."
    }
}
```

O `request_id` deverá permitir localizar o erro nos logs.

O roadmap da Fundação exige respostas padronizadas e proíbe exposição de stack trace em produção.

---

# 11. LOGS

Implemente logs estruturados.

Sempre que tecnicamente possível, incluir:

```text
request_id
tenant_id
user_id
module
action
exception
timestamp
```

Nunca registrar:

```text
senha
token
secret
certificado
dados pessoais desnecessários
```

Essa separação entre logs técnicos e auditoria já está prevista no roadmap.

---

# 12. AUDITORIA

**Log técnico e Audit Log são conceitos diferentes.**

Audit Log responde:

> Quem fez o quê?

Log técnico responde:

> O que aconteceu com a aplicação?

As operações sensíveis deverão registrar auditoria.

Exemplos:

```text
USER_LOGIN
USER_LOGIN_FAILED
USER_CREATED
USER_UPDATED
USER_DISABLED

COMPANY_CREATED
COMPANY_UPDATED

BRANCH_CREATED
BRANCH_UPDATED

ROLE_CHANGED
PERMISSION_CHANGED
```

Esses eventos são previstos no roadmap.

---

# 13. CÓDIGO COMENTADO EM PORTUGUÊS

Esta é uma regra permanente do projeto.

Todo comentário criado para explicar:

- regra de negócio;
- decisão arquitetural;
- trecho não trivial;
- workaround;
- tratamento especial;
- integração;
- segurança;
- comportamento incomum;

deverá ser escrito em **português do Brasil**.

Exemplo correto:

```php
// O tenant deve ser definido antes da execução da consulta
// para impedir acesso acidental a registros de outra empresa.
```

Exemplo incorreto:

```php
// Set tenant before query.
```

---

# 14. NÃO COMENTE O ÓBVIO

Não quero código poluído como:

```php
// Cria usuário.
$user = User::create($data);

// Retorna usuário.
return $user;
```

Comentários devem explicar **por quê**, e não simplesmente repetir **o que** a linha faz.

Exemplo útil:

```php
// O usuário não pode escolher o tenant pelo payload da requisição.
// O tenant sempre deve ser obtido do contexto autenticado para evitar
// alteração maliciosa do tenant_id e vazamento de dados entre clientes.
$tenantId = $tenantContext->id();
```

---

# 15. DOCBLOCKS

Classes e métodos importantes poderão possuir PHPDoc em português.

Exemplo:

```php
/**
 * Provisiona a estrutura inicial de um novo cliente da plataforma.
 *
 * Tenant, empresa, filial inicial, usuário proprietário e permissões
 * são criados dentro da mesma transação para impedir provisionamento
 * parcial em caso de falha.
 */
final class ProvisionTenantAction
{
}
```

---

# 16. NOMES DO CÓDIGO

Apesar dos comentários e documentação serem em português, mantenha nomes técnicos do código em inglês quando isso favorecer padrão internacional.

Exemplo:

```text
Tenant
Company
Branch
User
Permission
AuditLog
TenantContext
ProvisionTenantAction
```

Não utilizar:

```text
EmpresaController
FilialRepository
UsuarioService
```

misturado com Laravel em inglês sem uma convenção definida.

A regra será:

> **Código e domínio técnico prioritariamente em inglês; comentários, documentação e explicações em português.**

---

# 17. ARQUITETURA — MODULAR MONOLITH

Utilize a arquitetura definida no roadmap:

```text
app/
└── Modules/
    ├── Core/
    ├── Tenancy/
    ├── Companies/
    ├── Branches/
    ├── Identity/
    ├── Authorization/
    └── Audit/
```

O roadmap determina explicitamente **Modular Monolith** e orienta contra microserviços nesta fase.

Não introduza microserviços prematuramente.

---

# 18. ORGANIZAÇÃO INTERNA DOS MÓDULOS

Quando necessário:

```text
Module/
│
├── Domain/
├── Application/
├── Infrastructure/
└── Http/
```

Exemplo:

```text
Tenancy/
│
├── Domain/
│   ├── Models/
│   ├── Events/
│   └── Exceptions/
│
├── Application/
│   ├── Actions/
│   └── DTOs/
│
├── Infrastructure/
│   └── Persistence/
│
└── Http/
    ├── Controllers/
    ├── Requests/
    └── Resources/
```

Não crie camadas vazias apenas para aparentar arquitetura.

Utilize-as quando houver responsabilidade real.

---

# 19. CONTROLLERS DEVEM SER FINOS

Proibido criar controllers que concentrem:

```text
Query
+
Regra de negócio
+
Auditoria
+
Email
+
Persistência
+
Integração
```

Preferir:

```text
Controller
   ↓
Request
   ↓
DTO
   ↓
Action
   ↓
Domain
```

Controllers deverão:

```text
Receber
Validar
Delegar
Responder
```

O roadmap determina explicitamente essa separação.

---

# 20. MULTI-TENANCY É REQUISITO CRÍTICO

O projeto deverá utilizar:

```text
tenant_id
```

nas tabelas multi-tenant.

A arquitetura prevista utiliza:

```text
TenantContext
Global Scopes
Policies
Middleware
Jobs conscientes do Tenant
Testes automatizados de isolamento
```

como mecanismos de defesa.

Nunca confiar apenas em:

```php
where('tenant_id', ...)
```

espalhado manualmente.

Crie uma estratégia consistente e testável.

---

# 21. PROIBIDO VAZAMENTO CROSS-TENANT

Crie testes desde o primeiro momento para garantir:

```text
Tenant A ≠ Tenant B
```

Casos obrigatórios:

```text
Tenant A não lê Tenant B
Tenant A não atualiza Tenant B
Tenant A não exclui Tenant B
Tenant A não pesquisa Tenant B
Tenant A não recebe cache de Tenant B
Job de Tenant A não executa em Tenant B
```

O roadmap exige explicitamente esses testes como suíte permanente de regressão.

---

# 22. IDENTIFICADORES

Utilize preferencialmente:

```text
ULID
```

para entidades importantes, conforme recomendação do roadmap.

Motivos:

- não previsível;
- apropriado para sincronização futura;
- geração distribuída;
- evita exposição simples de contadores;
- adequado ao futuro PDV offline.

A escolha de ULID está documentada no roadmap.

---

# 23. MYSQL

Banco central:

```text
MySQL
```

Responsável pela verdade central do ERP.

A arquitetura oficial define:

```text
MySQL
→ verdade central do ERP

SQLite
→ operação local futura do PDV

Redis
→ cache, filas e coordenação
```



---

# 24. REDIS

Redis deverá ser preparado para:

```text
Cache
Queues
Locks
Rate Limiting
```

Não utilizar Redis como banco primário.

O roadmap da Fundação já define essas responsabilidades.

---

# 25. CACHE MULTI-TENANT

Toda chave de cache relacionada a dados do cliente deverá incorporar o tenant.

Exemplo:

```text
tenant:{tenant_id}:resource:{resource_id}
```

Nunca permitir colisão de cache entre tenants.

---

# 26. FILAS

Criar inicialmente filas como:

```text
default
notifications
audit
```

Arquitetura preparada para:

```text
fiscal
sync
intelligence
```

futuramente.

Todo Job multi-tenant deverá carregar `tenant_id` e restaurar seu contexto antes da execução.

---

# 27. AUTENTICAÇÃO

Seguir o roadmap utilizando inicialmente:

```text
Laravel Sanctum
```

Implementar:

```text
login
logout
me
forgot password
reset password
change password
```

Conforme definido na Fundação.

---

# 28. AUTORIZAÇÃO

Estruture:

```text
User
 ↓
Role
 ↓
Permission
 ↓
Resource
```

Papéis iniciais:

```text
OWNER
ADMIN
MANAGER
OPERATOR
VIEWER
```

Mas não utilize apenas:

```text
role == admin
```

como autorização.

Permissões deverão ser granulares e futuramente limitar acesso por filial.

---

# 29. API-FIRST

Toda API deverá nascer versionada:

```text
/api/v1/
```

Utilize:

- Form Requests;
- API Resources;
- DTOs;
- Actions;
- Policies;
- tratamento global de exceções.

Não retornar Eloquent Models diretamente.

---

# 30. OPENAPI

Mantenha documentação OpenAPI atualizada conforme os endpoints forem sendo criados.

Ela será essencial para integração futura com:

```text
PDV .NET/C#
```

---

# 31. MIGRATIONS

Toda migration deverá ser:

- pequena;
- revisável;
- segura;
- testada;
- reversível quando tecnicamente possível.

**Nunca altere migration já aplicada em produção futuramente.**

Crie nova migration para correções.

---

# 32. TRANSAÇÕES EM MIGRATIONS E DADOS

Não confunda rollback de migration com rollback transacional de regra de negócio.

Operações de negócio compostas deverão usar transações explicitamente.

Exemplo:

```php
DB::beginTransaction();

try {

    $tenant = // ...

    $company = // ...

    $branch = // ...

    $owner = // ...

    DB::commit();

    return $tenant;

} catch (\Throwable $exception) {

    DB::rollBack();

    Log::error(
        'Falha ao provisionar tenant.',
        [
            'exception' => $exception,
        ]
    );

    throw $exception;
}
```

---

# 33. TESTES DE ROLLBACK

Crie testes especificamente para provar rollback.

Exemplo:

```text
Given:
Provisionamento iniciado

When:
Criação do Owner falha

Then:
Tenant não permanece

And:
Company não permanece

And:
Branch não permanece
```

Não considere a transação correta simplesmente porque existe `DB::transaction()` no código.

**Teste a atomicidade.**

---

# 34. TESTES AUTOMATIZADOS

A implementação de cada etapa deve vir acompanhada de seus testes.

Tipos esperados:

```text
Unit
Feature
Integration
Security
Tenant Isolation
Regression
```

Para infraestrutura real, quando viável, testar contra:

```text
MySQL
Redis
```

e não somente mocks.

---

# 35. TEST-DRIVEN QUANDO ÚTIL

Não é obrigatório escrever todos os testes antes do código.

Entretanto, para bugs, isolamento multi-tenant e regressões críticas:

1. crie primeiro um teste que reproduza o problema;
2. confirme que falha;
3. implemente a correção;
4. confirme que passa.

---

# 36. NÃO CORRIJA TESTE PARA FAZER CÓDIGO ERRADO PASSAR

Caso um teste corretamente especificado falhe:

**corrija o código.**

Não enfraqueça:

- assertion;
- isolamento;
- permission;
- validação;
- regra;

apenas para tornar o pipeline verde.

---

# 37. CI

Criar pipeline contendo progressivamente:

```text
Checkout
 ↓
Install Dependencies
 ↓
Code Style
 ↓
Static Analysis
 ↓
Migrations
 ↓
Tests
 ↓
Build
```

Uma etapa não poderá ser considerada concluída com CI quebrado.

---

# 38. CODE STYLE

Utilize:

```text
Laravel Pint
```

ou padrão equivalente oficialmente adequado ao projeto.

---

# 39. STATIC ANALYSIS

Configure análise estática PHP em nível razoável e aumente a rigidez progressivamente.

Não acumule centenas de erros técnicos para corrigir futuramente.

---

# 40. DOCUMENTAÇÃO CONTÍNUA

Além dos três arquivos oficiais, crie progressivamente:

```text
README.md
SECURITY.md

docs/
├── architecture/
├── roadmap/
├── api/
├── security/
├── operations/
└── adr/
```

---

# 41. ADR — ARCHITECTURE DECISION RECORD

Crie ADRs para decisões importantes.

Exemplos:

```text
ADR-001-modular-monolith.md
ADR-002-mysql.md
ADR-003-shared-database-multitenancy.md
ADR-004-ulid.md
ADR-005-redis.md
ADR-006-sanctum.md
```

Estrutura:

```text
Contexto
Decisão
Alternativas
Consequências
```

Comentários e ADRs deverão estar em português.

---

# 42. README

O README deverá ser atualizado progressivamente.

Ele deverá conter:

- objetivo;
- stack;
- arquitetura;
- instalação;
- execução;
- Docker;
- MySQL;
- Redis;
- migrations;
- seed;
- testes;
- documentação;
- roadmap;
- status atual.

---

# 43. STATUS CENTRAL DO PROJETO

Crie ou mantenha um arquivo:

```text
PROJECT_STATUS.md
```

na raiz.

Esse arquivo deverá funcionar como painel rápido do desenvolvimento.

Exemplo:

```markdown
# Status do Projeto LUCRAONE

Atualizado em: YYYY-MM-DD HH:mm

## Fase Atual

FASE 01 — FOUNDATION

Status: 🟡 IN_PROGRESS

Progresso: 18%

## Sprint Atual

F1.1 — Bootstrap

Status: 🟡 IN_PROGRESS

## Última etapa concluída

✅ Configuração inicial do MySQL

## Em andamento

🟡 Configuração do Redis

## Próxima etapa

⬜ Docker

## Bloqueios

Nenhum.

## Testes

Unit: 24/24
Feature: 11/11
Integration: 8/8

Total: 43 passing

## Última auditoria

Sem erros críticos.
```

Atualize esse arquivo sempre que uma etapa relevante for concluída.

---

# 44. CHANGELOG DE DESENVOLVIMENTO

Mantenha também:

```text
CHANGELOG.md
```

ou:

```text
docs/DEVELOPMENT_LOG.md
```

registrando mudanças importantes.

Exemplo:

```markdown
## 2026-08-13

### Adicionado

- Bootstrap inicial Laravel.
- Docker.
- MySQL.
- Redis.

### Segurança

- Rate limiting inicial.

### Testes

- Criados testes de infraestrutura.

### Roadmap

- Sprint F1.1 avançou para 60%.
```

---

# 45. GIT

Caso a pasta ainda não possua repositório:

```bash
git init
```

Crie `.gitignore` adequado.

Não versionar:

```text
.env
vendor/
node_modules/
certificados
secrets
logs
credenciais
arquivos temporários
```

---

# 46. COMMITS

Faça commits pequenos e coerentes após conjuntos funcionais concluídos.

Convenção recomendada:

```text
feat:
fix:
test:
refactor:
docs:
chore:
```

Exemplo:

```text
feat(tenancy): implementa contexto multi-tenant

test(tenancy): adiciona testes de isolamento

docs(foundation): atualiza status da sprint F1.2
```

Não faça commit de funcionalidade que sabidamente quebra testes.

---

# 47. NÃO FAÇA COMMIT DE SEGREDOS

Antes de commit:

```text
.env
password
token
API key
certificado
private key
```

devem ser obrigatoriamente verificados.

---

# 48. SEGURANÇA

Desde a Fase 01 considere:

- SQL Injection;
- XSS;
- CSRF quando aplicável;
- IDOR;
- mass assignment;
- privilege escalation;
- broken access control;
- cross-tenant access;
- brute force;
- rate limiting;
- secret leakage;
- sensitive logging.

A Fundação determina explicitamente que segurança não é fase posterior.

---

# 49. IDOR

Este será um dos riscos mais críticos.

Nunca faça:

```php
Company::findOrFail($id);
```

e confie apenas no identificador recebido.

A consulta precisa necessariamente respeitar:

```text
Tenant
+
Authorization
+
Resource ownership
```

---

# 50. HEALTH CHECK

Implemente health checks progressivamente para:

```text
Application
MySQL
Redis
Queue
```

Nunca exponha credenciais ou detalhes sensíveis nesses endpoints.

---

# 51. AMBIENTES

Preparar:

```text
local
testing
staging
production
```

Nunca utilizar configurações de produção durante testes locais.

---

# 52. TIMEZONE

Persistência:

```text
UTC
```

Apresentação:

```text
Timezone do tenant/filial
```

Inicialmente:

```text
America/Sao_Paulo
pt-BR
BRL
```

quando apropriado.

---

# 53. SEEDERS

Seeders deverão ser utilizados para:

```text
roles
permissions
dados técnicos
```

Dados de demonstração devem ser separados dos seeders essenciais.

---

# 54. FACTORIES

Crie factories desde cedo para entidades importantes:

```text
TenantFactory
CompanyFactory
BranchFactory
UserFactory
```

Elas serão essenciais para testes cross-tenant.

---

# 55. NÃO IMPLEMENTE O PDV AGORA

Embora a arquitetura final preveja:

```text
.NET/C#
SQLite
Offline-First
```

não desenvolva o PDV nesta primeira implementação da Fundação, exceto quando alguma pequena prova técnica estiver explicitamente prevista no roadmap macro.

O objetivo atual é construir corretamente a plataforma central.

---

# 56. NÃO IMPLEMENTE PRODUCT CORE ANTES DA FOUNDATION

Mesmo que pareça tentador iniciar:

```text
products
categories
prices
inventory
```

não avance enquanto a Fundação não cumprir sua Definition of Done.

---

# 57. PROCESSO OBRIGATÓRIO PARA CADA ETAPA

Para cada item do roadmap:

### Passo 1 — Ler

Leia a especificação correspondente.

### Passo 2 — Analisar

Identifique:

- dependências;
- riscos;
- migrations;
- segurança;
- testes.

### Passo 3 — Implementar

Implemente somente o necessário.

### Passo 4 — Testar

Execute testes relevantes.

### Passo 5 — Auditar

Revise:

```text
segurança
tenant isolation
transactions
exception handling
logs
performance óbvia
code quality
```

### Passo 6 — Documentar

Atualize documentação e status.

### Passo 7 — Commit

Somente quando estiver consistente.

### Passo 8 — Avançar

Prossiga para a próxima etapa.

---

# 58. TRATAMENTO DE FALHA DURANTE O DESENVOLVIMENTO

Quando um comando ou implementação falhar:

**não abandone a etapa imediatamente.**

Faça:

```text
Erro
 ↓
Investigar
 ↓
Identificar causa
 ↓
Corrigir
 ↓
Reexecutar
 ↓
Testar
 ↓
Documentar se relevante
```

Não esconda falhas alterando configurações para ignorar erros.

---

# 59. NÃO DESABILITE SEGURANÇA PARA PASSAR TESTE

Proibido resolver problemas fazendo coisas como:

```text
desabilitar middleware
remover policy
permitir qualquer tenant
remover validação
silenciar exception
desabilitar foreign key
```

sem uma justificativa arquitetural sólida e documentada.

---

# 60. AUTONOMIA

Você possui autonomia para:

- criar arquivos;
- criar pastas;
- alterar arquivos;
- refatorar;
- instalar dependências necessárias;
- executar Composer;
- executar Artisan;
- executar migrations;
- executar testes;
- criar Docker;
- configurar Redis;
- configurar MySQL;
- criar CI;
- criar scripts;
- criar documentação;
- criar ADRs;
- inicializar Git;
- realizar commits locais;

**desde que as ações ocorram dentro do escopo de `D:\PROJETO-LUCRAONE` e respeitem os três documentos oficiais.**

---

# 61. EVITE PERGUNTAS DESNECESSÁRIAS

Não interrompa o desenvolvimento perguntando coisas que podem ser determinadas:

- pelos documentos;
- pela arquitetura existente;
- por padrões consolidados do Laravel;
- pela análise do projeto.

Quando houver uma pequena ambiguidade técnica, tome uma decisão razoável, registre-a em ADR quando relevante e continue.

Somente considere bloqueio quando existir uma decisão de negócio impossível de inferir com segurança.

Nesse caso:

```text
🔴 BLOCKED
```

deve aparecer no status.

---

# 62. NÃO REESCREVA O ROADMAP PARA FACILITAR O TRABALHO

Os roadmaps são especificações.

Não remova requisito difícil simplesmente porque aumentaria o esforço.

Caso alguma especificação precise tecnicamente ser alterada:

1. documente o motivo;
2. crie ADR;
3. mantenha rastreabilidade;
4. destaque a alteração no status;
5. preserve o objetivo original.

---

# 63. FINALIZAÇÃO DE CADA SPRINT

Antes de marcar uma sprint como concluída, execute:

```text
composer test / php artisan test
        ↓
Static Analysis
        ↓
Laravel Pint
        ↓
Migration validation
        ↓
Tenant Isolation Tests
        ↓
Security Review
        ↓
Documentation Review
        ↓
Status Update
```

Somente então:

```text
Status: ✅ DONE
```

---

# 64. FINALIZAÇÃO DE CADA FASE

Nenhuma fase será concluída somente porque suas telas ou endpoints funcionam.

Uma fase somente poderá receber:

```text
✅ DONE
```

quando cumprir:

```text
Implementação
+
Testes
+
Segurança
+
Auditoria
+
Documentação
+
Critérios de aceite
+
Definition of Done
```

---

# 65. AO FINAL DE CADA CICLO DE TRABALHO

Apresente um relatório conciso no terminal contendo:

```text
=========================================================
LUCRAONE — STATUS DO DESENVOLVIMENTO
=========================================================

Fase:
FASE 01 — FOUNDATION

Status:
🟡 IN_PROGRESS

Sprint:
F1.1 — Bootstrap

Progresso:
XX%

Implementado:
- ...
- ...

Arquivos principais alterados:
- ...
- ...

Banco:
- migrations: OK
- rollback: OK

Testes:
- Unit: XX passing
- Feature: XX passing
- Integration: XX passing
- Failed: 0

Segurança:
- ...

Pendências:
- ...

Próxima etapa:
- ...

=========================================================
```

---

# 66. REGRA DE QUALIDADE MAIS IMPORTANTE

Sempre prefira:

```text
Código simples
+
testado
+
transacional
+
auditável
+
bem documentado
```

a:

```text
Código sofisticado
+
difícil de manter
+
sem testes
+
sem rastreabilidade
```

---

# 67. PRINCÍPIO DE MANUTENÇÃO

Considere desde a primeira linha que futuramente outro desenvolvedor poderá receber este projeto sem conhecer seu histórico.

Ele deverá conseguir entender:

- por que uma arquitetura foi escolhida;
- como uma regra funciona;
- onde uma responsabilidade está localizada;
- como testar;
- como reproduzir;
- como fazer rollback;
- como identificar uma falha;
- qual fase está concluída;
- o que ainda falta desenvolver.

Por isso:

> **Código não óbvio deve possuir comentário útil em português; decisões importantes devem possuir documentação; e toda fase deve possuir rastreabilidade de implementação e testes.**

---

# 68. PRIMEIRA TAREFA

Agora inicie o trabalho em:

```text
D:\PROJETO-LUCRAONE
```

Execute nesta ordem:

```text
1. Inspecione todo o conteúdo atual da pasta.

2. Leia integralmente os três arquivos Markdown oficiais.

3. Verifique ferramentas disponíveis:
   - PHP
   - Composer
   - MySQL
   - Redis
   - Docker
   - Git
   - Node, se necessário.

4. Não altere os documentos originais antes de compreendê-los.

5. Crie um diagnóstico inicial do ambiente.

6. Crie/atualize:
   PROJECT_STATUS.md

7. Marque:
   FASE 01 — FOUNDATION
   como 🟡 IN_PROGRESS.

8. Identifique a primeira sprint real da Fundação.

9. Inicie a implementação.

10. Crie testes junto com cada funcionalidade.

11. Utilize transações com commit/rollback em qualquer operação composta.

12. Mantenha comentários relevantes em português.

13. Atualize os status conforme concluir cada item.

14. Não avance para Product Core enquanto a Foundation não atingir os critérios definidos no roadmap.
```

---

# 69. RESULTADO ESPERADO DA FOUNDATION

Ao término da fase deverá ser possível comprovar:

```text
Laravel funcionando
        ↓
MySQL funcionando
        ↓
Redis funcionando
        ↓
Tenant
        ↓
Company
        ↓
Branch
        ↓
User
        ↓
Authentication
        ↓
Authorization
        ↓
Tenant Isolation
        ↓
Audit
        ↓
API
        ↓
Tests
        ↓
Security
```

O roadmap define justamente que a aplicação deve ser capaz de subir o ambiente, conectar MySQL e Redis, criar Tenant, Empresa, Filial e Usuário, autenticar, identificar tenant, autorizar acesso, registrar auditoria e responder pela API.

---

# 70. ORDEM FINAL

**Comece agora.**

Não responda apenas com um plano teórico.

Inspecione a pasta, leia os documentos e execute a primeira etapa real da Fase 01.

Mantenha os documentos como fonte de verdade.

Mantenha status atualizado.

Mantenha transações seguras.

Mantenha tratamento de exceções.

Mantenha testes.

Mantenha auditoria.

Mantenha comentários relevantes em português.

E somente considere uma etapa ou fase concluída quando houver evidência técnica de que ela realmente foi concluída.