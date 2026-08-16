# Reporting & Analytics — Arquitetura e Guia

**Sprint:** F2.4 · **Data:** 2026-08-16
**Módulo:** `app/Modules/Reporting`

---

## 1. Decisão de escopo: relatórios fixos, não construtor de relatórios

O roadmap previa modelos `Report` (query definition, schedule, recipients) e
`Dashboard` (widget definitions) — ou seja, um motor genérico onde o usuário
monta o próprio relatório.

**Entregamos relatórios fixos, calculados sob demanda.** Motivos:

1. Os cinco endpoints que o próprio roadmap lista são todos relatórios
   determinados (vendas, estoque, clientes, KPIs, tendências). Nenhum deles
   precisa de definição dinâmica.
2. Guardar "query definition" editável pelo usuário e executá-la é uma
   superfície de injeção que exige um interpretador próprio e whitelist de
   colunas — trabalho maior que a sprint inteira, para uma demanda que ainda
   não existe.
3. Sem tabelas novas, o relatório nunca fica desatualizado em relação ao dado.

**Consequência aceita:** um relatório novo exige código. Quando aparecer demanda
real de relatório sob medida, o caminho natural é uma tabela de *filtros salvos*
(o usuário guarda combinações de período/empresa/categoria), não um interpretador
de query.

---

## 2. Estrutura

```
app/Modules/Reporting/
├── Domain/
│   └── ReportPeriod.php            valor: início, fim, granularidade, fuso
├── Application/
│   ├── DateBucket.php              SQL de agrupamento por data, por driver
│   ├── SalesReportService.php      série, totais, situação, top produtos
│   ├── InventoryReportService.php  posição, valor a custo/venda, situação
│   ├── CustomerReportService.php   top clientes, segmentação, totais
│   ├── DashboardSummaryService.php KPIs + comparação com período anterior
│   └── TrendAnalysisService.php    tendência e projeção
├── Http/
│   ├── Controllers/                Report · Dashboard · Analytics
│   └── Requests/ReportPeriodRequest.php
└── Routes/api.php
```

Web: `app/Http/Controllers/Web/ReportWebController.php` + `resources/views/reports/`.

---

## 3. As definições que os números seguem

Estas definições vivem em um lugar só justamente para que tela, API e CSV nunca
discordem.

| Conceito | Definição | Onde vive |
|---|---|---|
| **Faturamento** | pedidos `shipped` + `completed` | `Order::scopeRevenue()` |
| **Carteira em aberto** | pedidos `draft` + `pending` + `confirmed` | `Order::scopeBacklog()` |
| **Data do recorte** | `orders.created_at` (data em que a venda foi feita) | `SalesReportService` |
| **Ticket médio** | faturamento ÷ pedidos faturados | `SalesReportService::totais()` |
| **Valor a custo** | Σ (quantidade em mãos × preço de custo) | `InventoryReportService` |
| **Valor a venda** | Σ (quantidade em mãos × preço de venda) | `InventoryReportService` |
| **Período anterior** | intervalo de mesmo tamanho imediatamente antes | `ReportPeriod::anterior()` |

**Cuidado com `scopeOpen()` vs `scopeBacklog()`.** `open()` significa "ainda em
movimento" e inclui `shipped`; usá-lo para carteira contaria o pedido enviado
duas vezes — uma como receita, outra como carteira. Foi exatamente esse o bug
pego pelo teste `test_sales_report_counts_only_shipped_and_completed`.

Os escopos qualificam a coluna (`orders.status`) porque o relatório de clientes
faz join com `customers`, que também tem `status`.

---

## 4. Agrupamento por data em dois bancos

MySQL roda em dev/produção, SQLite roda nos testes, e as funções de data dos dois
não coincidem. `DateBucket` monta a expressão por driver:

| Granularidade | MySQL | SQLite |
|---|---|---|
| dia | `DATE_FORMAT(col, '%Y-%m-%d')` | `strftime('%Y-%m-%d', col)` |
| mês | `DATE_FORMAT(col, '%Y-%m')` | `strftime('%Y-%m', col)` |
| ano | `DATE_FORMAT(col, '%Y')` | `strftime('%Y', col)` |
| semana | `DATE_SUB(col, INTERVAL WEEKDAY(col) DAY)` | `date(col, 'weekday 0', '-6 days')` |

A semana é sempre identificada pela **segunda-feira** dela, nos dois bancos.
Formatos de número de semana (`%v`, `%u`, `%W`) divergem entre os drivers e
dariam baldes diferentes conforme o ambiente.

### Fuso horário

Timestamps são gravados em UTC; cada estabelecimento tem seu fuso. `DateBucket`
desloca a coluna pelo offset do tenant **antes** de agrupar — sem isso, uma venda
às 22h em Brasília cairia no dia seguinte do relatório.

**Limite conhecido:** o offset é fixo, calculado na data final do período. Regiões
que praticam horário de verão terão erro de uma hora nas datas de virada. O Brasil
não pratica desde 2019.

### Baldes vazios

`DateBucket::baldes()` gera todos os períodos do intervalo, e a série preenche com
zero os que não têm venda. Sem isso o gráfico mostraria só os dias com movimento,
e uma semana parada pareceria uma semana inexistente.

---

## 5. Projeção — o que ela é e o que não é

`TrendAnalysisService` traça uma **reta de mínimos quadrados** sobre a série do
período e projeta o balde seguinte.

- **Não** considera sazonalidade, feriado, campanha ou estoque disponível.
- Projeção negativa é cortada em zero — faturamento não fica negativo.
- Com menos de 3 baldes, `confiavel` volta `false` e a interface diz que o
  período é curto demais em vez de mostrar uma tendência inventada.
- Sem base anterior, a variação percentual volta `null` em vez de 100% — senão o
  primeiro mês de operação pareceria crescimento.

A resposta da API carrega o campo `metodo` dizendo isso explicitamente, e o modal
de ajuda repete em português de operador.

---

## 6. Autorização

Relatórios não têm modelo próprio, então não cabe Policy: a autorização é um
**Gate nomeado**, `view-reports`, registrado em `AppServiceProvider`.

```php
Gate::define('view-reports', fn (User $user) => $user->hasAnyPermission([
    'view-reports', 'manage-sales', 'create-role',
]));
```

O Gate é verificado **na API também** (via `ReportPeriodRequest::authorize()`),
não só na web. As APIs de F2.1–F2.3 não checam permissão — relatório expõe
faturamento agregado do estabelecimento e não deveria herdar essa lacuna.

A faixa comercial do `/dashboard` também respeita o Gate: quem não pode ver
relatório continua acessando o dashboard, só sem os números de faturamento.

---

## 7. Visualização

Sem biblioteca de gráficos: o projeto tem apenas Alpine e Tailwind, e um bundle
de charting não se paga para dois formatos. Os gráficos são HTML/CSS —
`x-chart-colunas` (série temporal) e `x-chart-barras` (ranking) — o que mantém
rótulo como texto real, sem distorção de `viewBox`.

### Cor

O laranja da marca (`sol` `#FF7A00`) rende **2,55:1** contra o fundo branco:
contraste baixo demais para preencher barra. Foi criado um passo mais escuro do
mesmo matiz para marcas de gráfico:

```css
--color-grafico: #d06400;   /* ≥3:1 sobre branco */
```

Validado com o script do guia de visualização: banda de luminosidade OK,
separação de 12,3 ΔE do `aco` sob protanopia, contraste ≥3:1. Botões e menu
seguem usando `sol` — a mudança vale só para marca de dado.

### Regras aplicadas

- Uma série → sem caixa de legenda (o título já diz o que está plotado). Com
  projeção viram duas séries e a legenda aparece.
- Colunas com no máximo 24px, topo arredondado em 4px, base quadrada, 2px de
  respiro entre vizinhas.
- Grade e eixos em hairline sólida, recessivos.
- Rótulo direto **só no pico** da série; nos rankings, todo valor é rotulado.
- Texto nunca veste a cor do dado — identidade vem da marca colorida ao lado.
- Situação de estoque usa a paleta reservada de estado, sempre com rótulo
  escrito junto: nunca só a cor.
- Todo gráfico tem "ver como tabela" abaixo, e cada coluna é alvo de foco por
  teclado com o mesmo conteúdo do hover.

O painel não tem modo escuro, então os gráficos também não têm.

---

## 8. Exportação

CSV via `streamDownload`, separador `;` e BOM UTF-8 para o Excel abrir a
acentuação. Streaming para que um estoque grande não precise caber na memória.
Tipos aceitos: `sales`, `inventory`, `customers` — restritos na rota, qualquer
outro dá 404.

---

## 9. Adiado

- **Scheduled report generation (emails).** Exigiria criar Mailable, Job e
  scheduler do zero (hoje o projeto não tem nenhum, e `MAIL_MAILER=log`). A F2.5
  — Advanced Automation já prevê "scheduled tasks (cron)" e "actions: send
  email"; montar a infraestrutura lá evita construir duas vezes.
- **Filtro por filial.** `orders.branch_id` existe mas nasce nulo — filiais ainda
  não têm tela no painel. Os serviços já aceitam o filtro `branch_id`.

---

## 10. Como testar manualmente

1. Entrar no painel e abrir o menu `relatórios`.
2. Conferir os KPIs contra o menu `vendas` — faturamento tem que bater.
3. Trocar o intervalo nos atalhos (7 / 30 / 90 dias) e ver os números mudarem.
4. Trocar o agrupamento para semana e mês; conferir que os baldes se juntam.
5. Passar o mouse numa coluna e abrir "ver como tabela".
6. Abrir `relatórios → estoque` e conferir valor a custo contra o menu `estoque`.
7. Abrir `relatórios → clientes` e conferir o topo contra o menu `clientes`.
8. Exportar cada relatório em CSV.
9. Entrar com usuário sem `view-reports` e confirmar 403 e dashboard sem a faixa.
