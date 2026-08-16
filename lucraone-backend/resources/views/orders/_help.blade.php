<x-modal nome="help-sales" titulo="ajuda de vendas">
    <div class="space-y-4 text-sm leading-6 text-aco">
        <p>
            vendas registra os pedidos do estabelecimento: quem comprou, o que comprou, por quanto e em que ponto o pedido está.
        </p>

        <div>
            <p class="font-semibold lowercase text-grafite">como funciona o fluxo</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li><strong>rascunho</strong> — o pedido está sendo montado; adicione e remova itens à vontade.</li>
                <li><strong>aguardando</strong> — pedido fechado, esperando a confirmação do cliente.</li>
                <li><strong>confirmado</strong> — o estoque é reservado; o saldo sai do disponível mas continua em mãos.</li>
                <li><strong>enviado</strong> — a reserva vira baixa definitiva no estoque.</li>
                <li><strong>concluído</strong> — pedido encerrado.</li>
                <li><strong>cancelado</strong> — se havia reserva, ela é devolvida ao disponível.</li>
            </ul>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">o que dá para fazer</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>buscar pedido por número ou por nome do cliente.</li>
                <li>filtrar por situação, empresa e cliente.</li>
                <li>escolher um cliente já cadastrado ou criar um novo na hora.</li>
                <li>adicionar itens usando o preço de venda do produto, ou informar outro valor.</li>
                <li>acompanhar subtotal, desconto e total do pedido.</li>
            </ul>
        </div>

        <p>
            itens só podem ser alterados enquanto o pedido está em rascunho ou aguardando. depois de confirmado o estoque já está reservado contra as quantidades atuais.
        </p>
    </div>
</x-modal>
