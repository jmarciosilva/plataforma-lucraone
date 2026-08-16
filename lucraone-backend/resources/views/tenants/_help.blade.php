<x-modal nome="help-tenants" titulo="ajuda de tenants">
    <div class="space-y-4 text-sm leading-6 text-aco">
        <p>
            tenant é o espaço isolado de um cliente dentro do LUCRAONE. Tudo que
            acontece no sistema fica separado por tenant: usuários, empresas,
            permissões, produtos e operações.
        </p>

        <div>
            <p class="font-semibold lowercase text-grafite">o que você pode fazer</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>listar tenants cadastrados e buscar por nome ou slug.</li>
                <li>criar um novo estabelecimento com plano, timezone, locale e moeda.</li>
                <li>editar dados cadastrais e status operacional.</li>
                <li>abrir o detalhe para ver usuários, empresas e dados de criação.</li>
                <li>arquivar um tenant sem apagar definitivamente, com opção de restaurar.</li>
            </ul>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">atenção aos status</p>
            <p class="mt-1">
                trial e active deixam o tenant operacional. suspended e cancelled
                indicam restrição ou encerramento. O arquivo usa exclusão lógica:
                o registro sai da lista padrão, mas pode voltar.
            </p>
        </div>
    </div>
</x-modal>
