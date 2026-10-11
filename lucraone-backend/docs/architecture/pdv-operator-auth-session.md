# Autenticação e sessão operacional do operador de PDV

Estado: implementado em `feat/pdv-operator-auth-session`; integração Java e
implantação pendentes de revisão. Nenhum merge na main, nenhum deploy, nenhuma
migration aplicada em produção.

PDV-BE-01 a PDV-BE-05 e o ciclo de credencial de máquina permanecem concluídos e
inalterados em contrato, exceto no ponto registrado em "Compatibilidade".
Fase 5 Java: EM ANDAMENTO / PARCIAL. Fase 6 não iniciada.

Contrato para quem implementa o cliente: [pdv-java-operator-integration.md](pdv-java-operator-integration.md).
Ciclo da credencial de máquina: [pdv-machine-credential-lifecycle.md](pdv-machine-credential-lifecycle.md).

## Duas identidades, nunca uma

A autorização de uma operação no caixa é a conjunção de cinco fatos, verificados
a cada requisição:

```text
TERMINAL AUTENTICADO + OPERADOR AUTENTICADO + TENANT AUTORIZADO
  + FILIAL AUTORIZADA + PERMISSÕES VÁLIDAS = SESSÃO OPERACIONAL AUTORIZADA
```

A credencial de máquina responde "este computador é um caixa legítimo desta
loja". Ela NÃO responde "há alguém no caixa", e nunca substitui o login humano.
A credencial do operador responde "esta pessoa se autenticou", e nunca substitui
a prova do terminal: ela só vale neste terminal, nesta instalação, sob a mesma
credencial de máquina com que nasceu.

Nenhum papel administrativo é atalho. Administrador do estabelecimento e
Platform Admin não operam caixa sem `access-pdv` e sem atribuição à filial —
autoridade para administrar não é autoridade para operar.

## Autorização por filial

`tenant_user` responde "esta pessoa é da loja" e a permissão responde "esta
pessoa pode usar o caixa". Nenhuma das duas responde "em QUAL filial", e é essa
a lacuna que `pdv_operator_branch_assignments` fecha. Sem ela, `access-pdv`
autorizaria todas as filiais do estabelecimento, inclusive as que a pessoa nunca
viu.

Uma linha por `(tenant_id, user_id, branch_id)`, com índice único
`pdv_operator_branch_unique`. Conceder depois de revogar reativa a MESMA linha,
em vez de criar uma segunda e deixar ambíguo qual vale. Histórico de quem
concedeu e quando fica no AuditLog, que já é o registro de rastreabilidade do
projeto.

Conceder exige, juntas e sem substituição entre si:

1. `BranchPolicy::update` sobre a filial — `manage-branches` no estabelecimento
   resolvido, filial do mesmo estabelecimento, empresa coerente. É a mesma
   autoridade que o `TerminalPolicy` já exige para administrar Terminal;
2. estrutura operacional — estabelecimento, empresa e filial ativos e coerentes,
   pela mesma definição que a máquina usa (`TerminalStructure`);
3. vínculo ativo da pessoa com o estabelecimento;
4. contenção de delegação (SEC-04 · E2) — a autoridade dos papéis do alvo
   contida na do ator.

A contenção tem UMA exceção, e ela é necessária: `access-pdv` é órfã de todos os
papéis padrão, inclusive do admin. Contando como autoridade do alvo, ninguém
administraria um operador — o administrador do estabelecimento seria recusado ao
revogar o acesso que ele mesmo concedeu. A exceção é exatamente a permissão que
o endpoint administra, e só ela; qualquer outra permissão do alvo continua tendo
de estar contida, então um gerente segue sem mexer no acesso de um administrador.
Por isso o `UserAdministrationGuard` não é chamado literalmente aqui: a regra é a
dele, com a exceção documentada.

Revogar roda na MESMA transação que encerra as sessões afetadas: a revogação não
pode ficar visível antes de as sessões pararem de valer.

## Permissão

`access-pdv` entra no catálogo de `StandardRoleMatrix` e em NENHUM papel padrão.
Em `user` ou `viewer` daria caixa a quem hoje só lê relatório; em `admin`
quebraria a matriz explícita de 26 permissões do SEC-04. O catálogo passou de 28
para 29 nomes, o admin continua com exatamente 26 e a contenção
`user ⊆ manager ⊆ admin`, `viewer ⊆ manager` permanece verificada.

A administração das atribuições reutiliza `manage-branches`. Uma permissão nova
para isso nasceria sem nenhum papel padrão e deixaria a capacidade inacessível a
quem administra o estabelecimento hoje.

A migration `2026_10_10_170200_add_access_pdv_permission_to_tenants` cria a LINHA
da permissão nos estabelecimentos existentes e nada mais: nenhuma concessão a
papel, nenhum usuário autorizado. Depois dela o número de pessoas com acesso ao
caixa continua sendo zero. Verificado no MySQL descartável: um estabelecimento
preexistente recebeu uma linha `access-pdv` e zero linhas em `role_permission`.

Nenhuma permissão de venda, desconto, pagamento, sangria ou fechamento foi
criada. Elas pertencem às fases que as definirem.

## Sessão operacional

Identidade própria, separada da sessão web, do PAT humano genérico, da credencial
de máquina e do futuro caixa financeiro. Reaproveitar um PAT humano faria o token
do turno valer em toda a API de negócio e faria a revogação do turno derrubar as
outras sessões da pessoa — escopos diferentes, e um deles preso a um computador.

O `id` da sessão é PÚBLICO e não autentica nada: serve a log, auditoria e lease.
Quem autentica é um segredo opaco de 32 bytes CSPRNG em 64 hex minúsculos,
entregue uma única vez. O banco guarda somente `credential_hash` = SHA-256, com
índice único; um dump não permite autenticar como ninguém. Não há selector
público: a busca é pelo hash do valor inteiro, então não sobra parte alguma para
vazar em log de proxy ou em URL. SHA-256, e não bcrypt, porque o segredo é de
alta entropia gerado pelo servidor — não há dicionário a resistir, e um hash
lento entraria em toda requisição do caixa. É o mesmo algoritmo que o Sanctum usa
nos PATs.

Política, em `config/pdv.php`:

```text
Uma sessão ativa por terminal.
Duração máxima: 8 horas (480 min), ABSOLUTA.
Nenhuma renovação automática além do prazo absoluto.
Logout encerra a sessão humana; NÃO despareia o terminal; NÃO fecha caixa financeiro.
```

A validade efetiva é o MENOR entre o teto de política e a expiração efetiva da
credencial de máquina que abriu a sessão. Uma sessão nunca sobrevive à máquina.

Quatro estados, e a transição é sempre terminal: de `ACTIVE` sai-se para `ENDED`
(logout), `EXPIRED` (prazo) ou `REVOKED` (administração ou perda de autorização),
e de nenhum deles se volta. Reativar uma conta não ressuscita a sessão.

A sessão é amarrada a seis campos, conferidos juntos a cada requisição:
terminal, estabelecimento, empresa, filial, instalação e id do PAT de máquina.
Cada um fecha um reuso diferente — outro terminal, outra instalação, outra
credencial de máquina (rotação), e terminal cujos vínculos mudaram depois do
login.

## Revalidação por requisição

Nada é herdado do login. `OperatorAuthorization` é a definição única, consultada
no login, na revalidação, em toda requisição protegida e na emissão do lease:

- terminal elegível (`TerminalAuthenticationEligibility`);
- conta ativa;
- vínculo ativo com o estabelecimento DO TERMINAL;
- `access-pdv` naquele estabelecimento;
- atribuição ativa à filial DO TERMINAL.

Desativar a conta, suspender o vínculo, revogar a filial ou retirar a permissão
derruba a sessão na requisição seguinte, sem depender de nenhum evento ter
rodado. Os ganchos de revogação em cascata — revogação de atribuição, saída do
Terminal de `ACTIVE` — apenas ANTECIPAM o efeito; não são a garantia. A
antecipação importa para o offline: um lease já emitido vale até o prazo dele, e
marcar a sessão revogada agora é o que faz a próxima reconexão recusar.

Limite conhecido, igual ao dos outros ganchos Eloquent do projeto: SQL direto,
bulk update e `saveQuietly` não passam por eles. A negação por requisição
continua valendo, porque ela lê o estado atual do banco.

## Concorrência

Toda transição serializa no Terminal, pelo mesmo `lockEligibleTerminal` do ciclo
de credencial. Lockar a sessão não bastaria: duas requisições simultâneas num
caixa livre não encontram linha alguma para lockar e criariam duas sessões.

A invariante "uma ativa por caixa" não pode ser índice: o MySQL 8 não tem índice
único parcial, e um único em `(terminal_id, status)` impediria dois turnos
`ENDED` no mesmo caixa — ou seja, o segundo turno do dia.

| Situação | Resultado |
|---|---|
| Terminal livre | 201, sessão nova |
| Mesmo operador, sessão válida e amarrada a esta máquina | 200, credencial rotacionada, prazo intacto |
| Mesmo operador, sessão amarrada a máquina anterior | antiga revogada, sessão nova |
| Outro operador com sessão ativa | 409 `operator_session_conflict` |
| Sessão vencida | reconciliada para `EXPIRED`, login novo permitido |
| Sessão revogada | nunca reutilizada |
| Falha de banco | sem estado parcial; a credencial só é entregue depois do commit |
| Duas requisições simultâneas | uma sessão ativa, nunca duas |

A recuperação pelo mesmo operador exige NOVA senha e rotaciona a credencial sem
mexer em `expires_at`. É a resposta à resposta perdida: o cliente repete o login
e recebe um segredo novo, em vez de o servidor guardar uma cópia do anterior em
cache de idempotência. Nenhum token operacional é guardado em cache comum.

## Offline

Desligado por padrão e enquanto não houver material criptográfico. Sem par de
chaves válido o recurso permanece desligado — não há assinatura simétrica, lease
sem assinatura nem geração automática de chave.

```text
Login do operador: ONLINE.
Sessão já autenticada: temporariamente válida offline.
Novo operador offline: NÃO permitido.
Reinício da aplicação offline: não restaura autorização automaticamente.
Sessão expirada: exige autenticação online.
Revogação conhecida: bloqueia imediatamente.
Prazo máximo: 120 min desde a última revalidação online bem-sucedida.
```

O lease é Ed25519 (libsodium, extensão `sodium` já usada pelo projeto): o Java
precisa VERIFICAR sem poder ASSINAR. `APP_KEY` está explicitamente fora — é
segredo simétrico do servidor, e distribuí-la a cada caixa faria qualquer caixa
poder emitir a própria autorização.

O prazo do lease é o MENOR entre a graça, o fim da sessão e a expiração efetiva
da credencial de máquina. O relógio do cliente não participa. Chave privada só no
backend; par incoerente, tamanho inválido, base64 inválido ou `key_id` ausente
deixam o recurso desligado, em vez de assinar com uma chave que o cliente não
verifica.

`GET` de sessão NÃO move `last_revalidated_at`: consultar o próprio estado não
deveria comprar tempo de operação sem rede. Só a revalidação explícita move.

Capacidades desta fase: `pdv:operator:session`, e nada mais. Venda, desconto,
pagamento, cancelamento, sangria e fechamento não são autorizados aqui, nem
antecipadamente. A sessão offline não cria autorização comercial fictícia.

`php artisan pdv:offline-lease-key --confirmar` imprime um par novo e não escreve
em `.env`, não grava arquivo e não roda sozinho. Chave privada operacional não é
criada nem publicada automaticamente.

## Auditoria

Ator humano EXPLÍCITO em todos os registros. Nas rotas de máquina
`$request->user()` é o Terminal, então `AuditLog::logAction()` — que lê
`auth()->user()` — gravaria o ULID do Terminal em `user_id` e atribuiria a ação
humana à máquina. `OperatorSessionAudit` recebe a pessoa por parâmetro; ela só é
nula quando o login falhou sem identificar conta alguma.

Eventos: `pdv.operator.login`, `login_failed`, `session_started`,
`session_recovered`, `session_revalidated`, `session_expired`, `session_revoked`,
`logout`, `branch_access_granted`, `branch_access_revoked`.

Nunca em `changes`: senha, credencial de sessão, credencial de máquina,
cabeçalho Authorization, hash de credencial, código de recuperação, segredo de
entrega, material do lease. A tentativa recusada grava um resumo truncado do
identificador (`identifier_fingerprint`), e não o e-mail: suficiente para agrupar
insistência, inútil para listar quem tem conta.

A auditoria da tentativa recusada é commitada ANTES de a recusa virar exceção —
um rollback apagaria o rastro de quem tentou.

## Enumeração e freios

Sete causas de recusa de login — senha errada, conta inexistente, conta inativa,
vínculo suspenso, permissão ausente, filial não atribuída, filial de outro
estabelecimento — saem com o MESMO status, o MESMO código e o MESMO corpo, byte a
byte exceto `request_id`. Verificado por teste. A senha é sempre verificada,
inclusive para e-mail sem conta, contra um hash descartável constante: é o que
iguala o tempo de resposta. A regra vive em `PasswordVerification`, extraída do
`AuthController` e compartilhada com ele, em vez de duplicada.

Freio do login em quatro dimensões, e nenhuma substitui as outras:

| Dimensão | Limite | Para o quê |
|---|---|---|
| Terminal | 10/min | caixa comprometido, que já chega autenticado como máquina |
| IP | 30/10 min | volume de uma origem |
| Identificador + terminal | 5/min | ataque a uma conta num caixa |
| Identificador agregado | 10/10 min | tentativa DISTRIBUÍDA, que passa pelas três acima |

O e-mail nunca entra em chave de cache em claro, só o resumo. O freio vale também
para a senha correta: um limitador que liberasse o acerto seguinte não freia
nada. Revalidação e logout: 30/min por terminal. Administração: 60/10 min por
usuário.

## Compatibilidade

A ability `pdv:operator-session` é nova e exigida nas quatro rotas de operador.
A emissão inicial e a promoção por rotação passaram a usar UM ÚNICO conjunto,
`MachineTokenAbility::paraCredencialDeMaquina()`:

```text
pdv:terminal:read  pdv:credential:renew  pdv:credential:confirm  pdv:operator-session
```

Antes a emissão nascia só com `pdv:terminal:read` e a rotação ampliava. Manter a
divergência faria um terminal recém-pareado não autenticar operador até
rotacionar — e a rotação dele só funciona por uma exceção de transição pensada
para credenciais que já estão em campo. Nunca `['*']`: a lista é explícita, e uma
ability criada depois não vale automaticamente para credenciais já distribuídas.

- credenciais NOVAS (pairing, administração) recebem a ability aprovada;
- candidatas promovidas por rotação recebem a ability aprovada;
- candidatas PENDENTES têm só `pdv:credential:confirm` e não autenticam operador;
- credenciais LEGADAS não recebem ampliação indiscriminada: não há update em
  massa de PAT. Um terminal legado adquire a nova ability pela rotação segura já
  implementada;
- nenhum token humano ganha ability de máquina.

**O Java precisa renovar a credencial legada antes de usar o contrato de
operador.** Uma credencial com exatamente `[pdv:terminal:read]` recebe 403
`forbidden` nas rotas de operador e continua funcionando em health, pairing, GET
de terminal e no ciclo de credencial.

Mudança de comportamento registrada: confirmar uma rotação com a credencial de
OUTRO terminal passou de 403 para 409 `credential_conflict`. A credencial agora
alcança a confirmação, e a recusa vem do serviço — `operation.terminal_id` tem de
bater com o terminal autenticado. O isolamento entre terminais é o mesmo; só o
código muda.

Health, pairing, `GET /terminal`, rotação, confirmação e recuperação mantêm seus
contratos. `PdvTerminalPayload` não foi alterado.

## Banco

Migrations aditivas, nenhuma alteração em tabela existente:

- `2026_10_10_170000_create_pdv_operator_branch_assignments_table`
- `2026_10_10_170100_create_pdv_operator_sessions_table`
- `2026_10_10_170200_add_access_pdv_permission_to_tenants`

FKs com `restrict`, exceto `granted_by` com `set null` — arquivar o administrador
não pode apagar a concessão nem travar a remoção da conta dele. `machine_token_id`
é referência histórica sem cascata, pelo mesmo motivo de
`terminal_credential_operations`: o Sanctum apaga tokens, e apagar o histórico da
sessão junto esconderia por que ela deixou de valer.

O índice único composto tem nome explícito (`pdv_operator_branch_unique`): o nome
gerado pelo Laravel tem 66 caracteres e estoura o limite de 64 do MySQL. O SQLite
da suíte padrão aceitaria e esconderia o problema — foi o MySQL descartável que o
apontou.

Aplicar as migrations ANTES do código e habilitar a integração Java só depois.
Esta branch não foi implantada. Rollback recomendado: código anterior, mantendo
as tabelas. `down()` existe e foi exercitado no MySQL descartável, mas descarta
concessões e histórico de turnos; encerrar as sessões em andamento sob controle
antes de executar.

`php artisan pdv:expire-operator-sessions` marca como `EXPIRED` as sessões
vencidas, preservando histórico. É higiene, não segurança — a expiração é
aplicada pela autenticação a cada requisição. Incluído, não executado em
produção, não agendado automaticamente.

## Limitações conhecidas

- credencial de sessão roubada representa o operador naquele terminal até o
  prazo, o logout ou a revogação. Não há prova de hardware, mTLS nem detecção de
  clonagem — mesma limitação já registrada para a credencial de máquina;
- rotacionar a credencial de máquina derruba o turno em andamento. É o requisito
  de revogação, e é por isso que a recomendação ao Java é rotacionar com o caixa
  sem operador autenticado;
- não há PIN nem segundo fator nesta entrega, por decisão de escopo;
- não há interface gráfica para administrar atribuições;
- revogação por SQL direto não dispara os ganchos de antecipação; a negação por
  requisição continua valendo;
- a graça offline pressupõe relógio do servidor no momento da emissão; um cliente
  com relógio adiantado não ganha prazo, porque o prazo vai assinado no lease.

## Validação

SQLite em memória, cache/session array, mail array, fila sync, sem rede.
Ambiente isolado em worktree próprio, com `.env` sintético e assets copiados.
Nenhum Artisan executado no container operacional, nenhuma migration aplicada no
banco operacional.

Baseline do HEAD anterior (`8f09d49`): 1.512 testes, 1.510 aprovados, 0 falhas,
0 erros, 0 skipped, 2 risky preexistentes.

Regressão final: 1.614 testes, 1.612 aprovados, 8.235 asserções, 0 falhas,
0 erros, 0 skipped, os mesmos 2 risky preexistentes. Foram acrescentados 102
testes.

MySQL 8.4 descartável (`phpunit.mysql-lifecycle.xml`, opt-in
`PDV_DISPOSABLE_MYSQL=1`, host `pdv-lifecycle-mysql`, base `pdv_lifecycle_test`,
rede dedicada sem portas, dados em tmpfs): 14 testes, 132 asserções, processos e
conexões independentes, migrations up/down exercitadas. Seis dos testes são de
sessão de operador.

Pint: aprovado nos 51 arquivos PHP alterados ou criados. PHPStan: 16 diagnósticos de arquivo e
um ignore não correspondente, exatamente o baseline — nenhum diagnóstico novo.

Não iniciar Fase 6, catálogo, vendas, pagamentos ou caixa financeiro nesta
entrega.
