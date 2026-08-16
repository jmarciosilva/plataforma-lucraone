<x-modal nome="help-reports" titulo="ajuda de relatórios">
    <div class="space-y-4 text-sm leading-6 text-aco">
        <p>
            relatórios respondem três perguntas: quanto o estabelecimento vendeu, quanto está parado no estoque e quem são os clientes que sustentam o faturamento.
        </p>

        <div>
            <p class="font-semibold lowercase text-grafite">o que conta como faturamento</p>
            <p class="mt-1">
                só pedidos <strong class="text-grafite">enviados</strong> e <strong class="text-grafite">concluídos</strong> — os que já saíram do estoque. rascunho, aguardando e confirmado aparecem separados, como <strong class="text-grafite">carteira em aberto</strong>. pedido cancelado não entra em nenhum dos dois.
            </p>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">como o período é contado</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>o recorte usa a <strong class="text-grafite">data de criação do pedido</strong> — a data em que a venda foi feita.</li>
                <li>as datas seguem o fuso do estabelecimento, não o do servidor.</li>
                <li>a comparação de variação é sempre contra o período anterior de mesmo tamanho.</li>
                <li>dias sem venda aparecem como zero no gráfico, para não esconder períodos parados.</li>
            </ul>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">valor do estoque</p>
            <p class="mt-1">
                o valor a custo diz quanto dinheiro está imobilizado; o valor a venda diz quanto isso vira se vender tudo. produto sem preço de custo cadastrado entra como zero e é contado à parte, para o número não mentir.
            </p>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">sobre a projeção</p>
            <p class="mt-1">
                é uma reta simples traçada sobre o próprio período, respondendo "se continuar assim, dá quanto?". <strong class="text-grafite">não considera sazonalidade, feriado nem campanha</strong> — trate como estimativa grosseira, nunca como previsão.
            </p>
        </div>

        <p>
            todo gráfico tem um "ver como tabela" logo abaixo, e cada relatório exporta em csv para abrir no excel.
        </p>
    </div>
</x-modal>
