# Central de cargas PDV — Fase 6.2A

## Pré-visualização segura

`PDV_CATALOG_UI_PREVIEW_ENABLED=true` permite que administradores autorizados abram a Central para avaliar a interface, os terminais e o heartbeat. `PDV_CATALOG_DISTRIBUTION_ENABLED` permanece independente e deve continuar `false` durante essa avaliação. Com distribuição desligada, o servidor responde 403 a qualquer POST de publicação antes de validar dados, criar publicação ou enfileirar job; o botão e as caixas de seleção também ficam desabilitados na página.

Se as tabelas de publicação, entrega, snapshot ou estado ativo estiverem ausentes, a tela continua exibindo apenas dados autorizados de empresas, filiais e terminais, e mostra o histórico como indisponível. Nenhum dado fictício é criado. O menu lateral usa as mesmas permissões de leitura da página. Desabilite `PDV_CATALOG_UI_PREVIEW_ENABLED` para ocultar a tela novamente. A feature de distribuição operacional exige autorização e preparação separadas.

## Operação administrativa

A tela `GET /pdv/catalog-loads` exige usuário web autenticado, membership ativa no tenant selecionado, leitura de empresas e filiais/terminais autorizada pelas policies existentes, além de `manage-products` para publicar. A publicação valida novamente tenant, empresa, filial, status do terminal, instalação e `TerminalPolicy::update` no POST. IDs enviados pelo navegador não definem o tenant.

Somente terminais `ACTIVE`, instalados e ligados a empresa e filial ativas podem receber uma publicação. O painel mostra status cadastral separadamente da conectividade. Heartbeat até 90 segundos é **online**; de 91 a 300 segundos é **comunicação atrasada**; sem heartbeat ou acima de 300 segundos é **offline**. `BLOCKED`, `REVOKED` e `PENDING` aparecem como não operacionais, independentemente do último heartbeat. A classificação usa `last_seen_at` produzido pelo endpoint autenticado de heartbeat; heartbeat não cria, altera nem transfere catálogo.

Um terminal fora da janela online só pode ser selecionado quando o administrador marcar explicitamente o agendamento offline. Sua entrega permanece `PENDING`; um novo heartbeat durante a geração ou a própria consulta autenticada do terminal a promove a `AVAILABLE`. Uma publicação em geração por empresa bloqueia outra geração simultânea. Quando uma nova versão está integralmente pronta, entregas ainda não aplicadas das versões anteriores são canceladas para os mesmos destinatários; a versão já aplicada no terminal não é removida até a aplicação válida da nova.

A Central permanece em `/pdv/catalog-loads`. A tela de detalhes do terminal oferece `cargas deste terminal`, levando à mesma Central filtrada para o destinatário; a tela de Terminais PDV também oferece o acesso geral. A autorização da Central é repetida no servidor. O filtro de terminal só aceita IDs presentes na coleção visível do usuário para a empresa autorizada. O histórico nessa navegação inclui apenas destinatários visíveis.

## Snapshot

Cada publicação recebe ULID, versão crescente por empresa, schema version 1, usuário, empresa, data, quantidades e SHA-256. O job lê em transação consistente (`REPEATABLE READ` em MySQL), percorre produtos em ordem de ID em lotes de 200, e grava registros JSON canônicos imutáveis. Produtos ativos e não arquivados da empresa são incluídos; produtos inativos, descontinuados e removidos ficam fora da nova versão. O Java substitui o catálogo local apenas depois de validar e aplicar a versão completa.

O registro NDJSON contém `id`, `sku`, `name`, `barcode`, `unit`, `status`, `sale_prices`, `packages` e `updated_at` em ISO 8601 UTC. `status` será `active`; somente produtos ativos e não arquivados entram. Preços são apenas do tipo `sale`, com `amount` decimal como string e moeda. Ausência de preço é `sale_prices: []`. Embalagens carregam ID, nome, código preservado como string e fator inteiro. Código de custo, margem, histórico de preço, fiscal e estoque não entram no snapshot.

Ordenação é determinística por produto ID; preços por produto e moeda; embalagens por produto e ID. O checksum da publicação é SHA-256 dos bytes UTF-8 dos registros JSON compactos em ordem, cada linha seguida de `\n`. Cada parte fornece seu próprio SHA-256 sobre os bytes NDJSON exatos. O terminal pode retomar na primeira parte após `last_part`; o servidor só aceita progresso sequencial. O schema muda por incremento explícito de `schema_version`.

## Distribuição para a máquina

Todos os endpoints exigem `Authorization: Bearer …`, `auth:sanctum`, `terminal.context`, terminal operacional e a ability exata `pdv:catalog:distribution`. Não aceitam `X-Tenant-ID`, credencial em query string ou sessão humana. O token só consulta entregas cujo `terminal_id` seja o sujeito autenticado, e o serviço também confere tenant e empresa da publicação contra o terminal autenticado. A distribuição não requer sessão de operador e não atualiza vendas nem estoque.

Estados da entrega: `PENDING` aguarda consulta do terminal ou agendamento offline; `AVAILABLE` pode iniciar download; `TRANSFERRING` indica manifesto consultado e partes em andamento; `RECEIVED` significa todos os segmentos verificados pelo cliente e checkpoint final confirmado; `APPLIED` significa que o cliente confirmou a troca local e atualiza a versão ativa; `FAILED` registra um código sanitizado; `CANCELLED` encerra uma versão substituída antes da aplicação. `FAILED`, `CANCELLED` e `APPLIED` não podem ser sobrescritos por outra transição. Consultas repetidas de confirmação retornam o mesmo estado quando a operação já foi concluída.

| Método e caminho | Uso |
| --- | --- |
| `GET /api/v1/pdv/catalog/deliveries/pending` | Lista entregas abertas da própria máquina. |
| `GET /api/v1/pdv/catalog/deliveries/{delivery}/manifest` | Lê versão, schema, checksum, quantidades, partes e checkpoint. |
| `GET /api/v1/pdv/catalog/deliveries/{delivery}/parts/{part}` | Baixa NDJSON, no máximo 200 produtos; resposta inclui checksum em `X-PDV-Catalog-Part-Checksum`. |
| `POST /api/v1/pdv/catalog/deliveries/{delivery}/progress` | Body `{"last_part":0}` após gravar e validar aquela parte localmente. Só aceita a próxima parte. |
| `POST /api/v1/pdv/catalog/deliveries/{delivery}/received` | Confirma a transferência completa; não significa que o SQLite já aplicou a carga. |
| `POST /api/v1/pdv/catalog/deliveries/{delivery}/applied` | Confirma aplicação atômica pelo Java e define a versão ativa do terminal. |
| `POST /api/v1/pdv/catalog/deliveries/{delivery}/failed` | Registra somente um código sanitizado: `CHECKSUM_MISMATCH`, `UNSUPPORTED_SCHEMA`, `STORAGE_ERROR` ou `APPLY_FAILED`. |

Repetir leitura de parte é seguro. O checkpoint só aceita sequência crescente sem saltos, depois de manifesto. Em snapshot não vazio, `received` exige que o checkpoint final (`part_count - 1`) esteja registrado; ausência de checkpoint não equivale à parte zero. Snapshot vazio tem `part_count: 0`, não possui endpoint de parte válido e só pode ser recebido após a consulta ao manifesto, sem checkpoint. Assim, o checksum SHA-256 vazio confirma o conteúdo zero. A entrega persiste `manifest_seen_at` e `transfer_verified_at`: registros `RECEIVED` antigos sem evidência de transferência precisam consultar novamente o manifesto e confirmar uma transferência completa antes de `applied`. Checkpoint, recebimento, aplicação e falha são serializados por lock da linha do terminal e da entrega. `applied` só aceita `RECEIVED` com `transfer_verified_at` ou repetição idempotente de `APPLIED`; `failed` aceita `PENDING`, `AVAILABLE`, `TRANSFERRING` ou `RECEIVED`, é idempotente para o mesmo código e não sobrescreve `APPLIED`/`CANCELLED`. Se aplicação e falha concorrerem, uma transição vence e a outra recebe conflito. Partes/manifesto têm `Cache-Control: private, no-store`. Erros seguem o envelope PDV com códigos estáveis, sem dados de outras empresas.

## Credenciais já emitidas

Foi criada a ability `pdv:catalog:distribution`, distinta de `pdv:catalog:read`. Ela é emitida em novas credenciais e na promoção da credencial candidata pelo fluxo já existente de rotação. Tokens antigos conservam suas abilities e recebem 403 até uma rotação segura normal. O deploy não rotaciona nem revoga credencial do `pdv-001`; nenhum segredo é necessário para esta API. A API de catálogo da Fase 6.1 e os contratos da Fase 5 permanecem sem alteração.

## Migrations e implantação

As migrations criam `pdv_catalog_publications`, `pdv_catalog_deliveries`, `pdv_catalog_snapshot_items` e `pdv_terminal_catalog_states`; uma migration incremental adiciona os marcadores `manifest_seen_at` e `transfer_verified_at` a bancos que já tenham as tabelas da publicação anterior. Elas não alteram produtos, preços, vendas, estoque, filiais ou terminais existentes. O item de snapshot usa `LONGTEXT` para preservar os bytes canônicos de cada registro; cada linha inclui produto, preços de venda e embalagens, sem custo, estoque ou histórico. Índices suportam versão por empresa, destino/status e sequência da parte. Não há limpeza automática: snapshots publicados e históricos são mantidos. A remoção segura depende de política de produto e retenção, pois as entregas e o estado ativo apontam para publicações. Antes de dimensionar retenção, medir volumes e distinguir versões com entregas pendentes, versões ativas e histórico concluído. Esta alteração requer migration; ela não deve ser aplicada automaticamente no banco operacional. A tela e os endpoints também ficam fechados por padrão; habilitar `PDV_CATALOG_DISTRIBUTION_ENABLED=true` somente depois de revisar e aplicar as migrations, e então reconstruir o cache de configuração em implantação autorizada.

Medição descartável em MySQL 8.4.11: 10.000 produtos, 10.000 preços de venda, 2.000 embalagens e payloads sintéticos compactos produziram snapshot de 2.853.064 bytes (média de 285,3 bytes por produto) em 3,279 s, com pico PHP de 28 MiB. O job consultou e gravou em lotes de 200. Linearmente, isso sugere cerca de 28,5 MB e 33 s para 100.000 registros e cerca de 285 MB por publicação para 1 milhão; extrapolações não substituem benchmark com descrições/preços/embalagens reais em ambiente de desenvolvimento. O timeout de 60 s é adequado para o volume medido, mas não para catálogos muito maiores; medir antes de escalar além de aproximadamente 100 mil produtos e aumentar timeout somente em conjunto com `retry_after` do worker (atualmente 90 s) ou particionar geração. A transação consistente cobre todo o snapshot, portanto duração e undo history crescem com o catálogo.

Rollback: interromper novas publicações, confirmar que não existe job em geração, exportar se necessário e executar a migration down somente após aprovação operacional; a remoção apaga históricos e snapshots dessa feature. Não é um rollback reversível de dados. A migration MySQL contém quatro `CREATE TABLE` sequenciais; como DDL faz commit implícito, falha parcial pode deixar as primeiras tabelas criadas, que precisam ser inspecionadas antes de repetir ou reverter.

## Testes

`tests/Feature/Pdv/PdvCatalogDeliveryTest.php` cobre snapshot sanitizado, preços e embalagens, destinatário, ability, progresso, bloqueio, confirmação de aplicação e estado ativo. Rodar com o banco descartável de PHPUnit. Não testar contra a base operacional nem criar publicação destinada a terminal real.
