<x-modal nome="help-automations" titulo="ajuda de automações">
    <div class="space-y-4 text-sm leading-6 text-aco">
        <p>
            uma automação é uma frase: <strong class="text-grafite">quando</strong> algo acontece,
            <strong class="text-grafite">se</strong> as condições baterem,
            <strong class="text-grafite">então</strong> faça isso.
        </p>

        <div>
            <p class="font-semibold lowercase text-grafite">quando — os gatilhos</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li><strong class="text-grafite">produto criado</strong> — um produto novo entrou no catálogo.</li>
                <li><strong class="text-grafite">estoque baixo</strong> — o saldo cruzou para baixo do ponto de reposição. dispara na travessia, não a cada saída, para não encher sua caixa de entrada.</li>
                <li><strong class="text-grafite">pedido concluído</strong> — um pedido chegou ao fim do fluxo de vendas.</li>
            </ul>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">se — as condições</p>
            <p class="mt-1">
                cada gatilho oferece seus próprios campos. todas as condições precisam ser verdadeiras ao mesmo tempo.
                sem condição, a regra vale sempre que o gatilho ocorrer. precisa de "ou"? crie duas regras.
            </p>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">então — as ações</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li><strong class="text-grafite">criar aviso no painel</strong> — aparece no menu avisos, para todo mundo do estabelecimento.</li>
                <li><strong class="text-grafite">enviar e-mail</strong> — para os endereços que você listar.</li>
                <li><strong class="text-grafite">atualizar preço</strong> — muda o preço do produto do gatilho. toda mudança fica no histórico de preços, e o ajuste percentual é limitado por execução.</li>
            </ul>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">escrevendo textos com dados</p>
            <p class="mt-1">
                no título, no assunto e na mensagem dá para usar <code class="rounded bg-nevoa px-1">{campo}</code>
                para inserir o dado do gatilho. exemplo: <em>"restam {quantidade} unidades de {produto}"</em>.
                nome de campo errado fica escrito literal, para você enxergar o engano.
            </p>
        </div>

        <p>
            regras rodam em segundo plano. se uma regra falha, as outras continuam, e o motivo fica no histórico —
            é o primeiro lugar para olhar quando algo não aconteceu como esperado.
        </p>
    </div>
</x-modal>
