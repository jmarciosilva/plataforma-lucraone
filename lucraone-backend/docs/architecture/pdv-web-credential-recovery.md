# Recuperação administrativa de credencial de terminal no painel

## Acesso e autorização

Na tela de detalhes de um terminal (`/terminals/{id}`), administradores com
`TerminalPolicy::update` podem iniciar **Gerar código de recuperação** quando o
terminal está `ACTIVE`, possui `installation_id` e o próprio domínio o considera
operacional. A política delega à autoridade de administração da filial no tenant
atual. O controller limita a busca pelo ID ao tenant resolvido pela sessão;
alterar o ID na URL não permite acessar outro estabelecimento.

A rota web `POST /terminals/{terminal}/credential-recovery` usa a sessão humana
do painel, middleware `auth.web`, CSRF e limite administrativo. Ela não usa nem
fabrica um PAT para `HumanAdministrationContext`, que é específico da API.
Autorização de filial e estado operacional são conferidos novamente no servidor
e dentro da transação de domínio.

## Emissão e estados

A ação chama exclusivamente
`TerminalCredentialLifecycle::authorizeRecovery($actor, $terminal, $reason)`.
Esse serviço verifica elegibilidade e permissão, invalida as credenciais de
máquina anteriores, emite uma operação `RECOVERY`, aplica a validade configurada
e grava auditoria sem o código. A ação não altera terminal, instalação,
empresa, filial ou operadores.

Motivos permitidos: `expired`, `lost`, `compromised`, `keyring-unavailable` e
`rotation-failed`. O rótulo `lost` também cobre credencial indisponível. Pairing
continua sendo uma operação distinta, exclusiva do fluxo de terminal `PENDING`;
recovery é para uma instalação já vinculada e elegível. Um terminal bloqueado
precisa ser desbloqueado administrativamente antes da recuperação; desbloqueio
não ressuscita credenciais invalidadas.

## Exibição do segredo

O código aparece somente na resposta JSON do POST autorizado, com
`Cache-Control: no-store, private`, `Pragma: no-cache`, `X-Robots-Tag` e
`Referrer-Policy: no-referrer`. O GET da tela não recebe o código. Alpine o
mantém temporariamente apenas na memória para exibição/cópia, não o coloca em
flash, cookie, armazenamento do navegador, URL, atributo persistente ou log, e
apaga o valor ao fechar o modal ou sair da página. A expiração vem do serviço e
é formatada no navegador em `America/Sao_Paulo`.

Em caso de falha de rede ou resposta de servidor inesperada, o resultado fica
marcado como incerto: a interface não reenvia a operação. O administrador deve
confirmar o estado antes de autorizar outro código. O domínio continua sendo a
autoridade para uso único, expiração e consumo.

## Uso no Java

1. Deixe o LucraOne PDV Java na tela “Terminal sem autorização”.
2. Digite o código no campo “Código de recuperação fornecido pelo administrador”.
3. Clique em “Recuperar credencial” e aguarde a confirmação da nova credencial.
4. Depois da validação, o Java volta à identificação do operador.

O protocolo Java permanece `POST /api/v1/pdv/terminal/credential/recover`, com
`recovery_code`, `installation_id` e `delivery_secret`, seguido da confirmação
da credencial candidata. Esta interface não modifica esse contrato nem foi
testada com um terminal físico.

## Verificação e publicação

`TerminalRecoveryWebTest` cobre visibilidade, CSRF, motivo, isolamento por
tenant, estados, emissão, auditoria, cabeçalhos de cache e sessão `cookie` sem
flash do segredo. Os testes de recuperação, rotação, pairing, bloqueio e
autorização continuam sendo a regressão dos contratos existentes. Não usar o
banco operacional em testes.

Deploy sem migrations: publicar os arquivos da aplicação, atualizar cache de
rotas/views e compilar assets quando necessário. Rollback: reverter o commit e
republicar/compilar assets. Nenhum código real deve ser emitido durante testes
ou implantação; o proprietário faz a recuperação manual após validar o painel.
