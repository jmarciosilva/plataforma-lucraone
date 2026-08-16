<x-modal nome="help-users" titulo="ajuda de usuários">
    <div class="space-y-4 text-sm leading-6 text-aco">
        <p>
            usuário é uma pessoa com conta global no LUCRAONE. O acesso dela a
            cada estabelecimento é controlado por um vínculo no tenant atual e
            por papéis específicos daquele tenant.
        </p>

        <div>
            <p class="font-semibold lowercase text-grafite">o que você pode fazer</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>listar somente usuários vinculados ao estabelecimento atual.</li>
                <li>criar usuário com nome, e-mail, senha, status e papéis.</li>
                <li>editar dados, trocar status no tenant e atualizar papéis.</li>
                <li>alterar ou resetar senha quando alguém perder acesso.</li>
                <li>arquivar usuário; se ele estiver em outros tenants, só o vínculo atual é desativado.</li>
                <li>restaurar usuário arquivado quando for necessário liberar acesso novamente.</li>
            </ul>
        </div>

        <div>
            <p class="font-semibold lowercase text-grafite">status do vínculo</p>
            <p class="mt-1">
                active permite acesso. invited indica convite pendente. inactive
                remove acesso local. suspended bloqueia temporariamente naquele
                estabelecimento sem afetar outros vínculos da mesma pessoa.
            </p>
        </div>
    </div>
</x-modal>
