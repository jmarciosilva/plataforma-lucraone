<x-layouts.app title="Como cadastrar um novo cliente no LucraOne" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo><span class="h-2 w-2 rounded-full bg-sol"></span>Orientação para operadores da plataforma</x-slot:subtitulo>
    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('tenants.index') }}">Voltar para clientes</x-button>
        <x-button href="{{ route('tenants.create') }}">Cadastrar novo cliente</x-button>
    </x-slot:acoes>

    <article class="space-y-6 text-sm leading-6 text-grafite">
        <x-card>
            <h2 class="text-lg font-semibold">Antes de começar</h2>
            <p class="mt-2">Um cliente do LucraOne é uma empresa ou estabelecimento que utilizará a plataforma para administrar suas operações. Para cadastrar um novo cliente, o sistema orientará você por quatro etapas.</p>
            <p class="mt-2">Tenha em mãos os dados básicos do negócio e o e-mail da pessoa responsável. A conta de operador da plataforma não assume automaticamente a administração do cliente.</p>
        </x-card>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-card>
                <h2 class="text-lg font-semibold">1 — Estabelecimento</h2>
                <p class="mt-2">Informe os dados básicos do cliente: Nome, Identificador, Plano, Situação, Fuso horário, Idioma e Moeda.</p>
                <p class="mt-2">O identificador é um nome curto, sem espaços, que ajuda o sistema a distinguir o estabelecimento. Por exemplo: <strong>Massas do João</strong> pode usar <strong>massas-do-joao</strong>. Se deixar esse campo vazio, ele será gerado a partir do nome.</p>
                <p class="mt-2">Confira o plano combinado e o fuso horário do negócio para que os horários sejam exibidos corretamente.</p>
            </x-card>
            <x-card>
                <h2 class="text-lg font-semibold">2 — Administrador</h2>
                <p class="mt-2">Informe quem será responsável por administrar este cliente: nome, e-mail e senha inicial quando for uma pessoa nova.</p>
                <p class="mt-2">Se o e-mail já estiver cadastrado no LucraOne e a conta estiver disponível, o sistema reutilizará essa pessoa e dará acesso ao novo estabelecimento. Seu nome será mantido e a senha atual não é alterada. Não é preciso criar outra conta ou informar uma nova senha.</p>
                <p class="mt-2">Se a conta estiver indisponível, o sistema avisará. Confira o e-mail ou escolha outra pessoa com conta ativa.</p>
            </x-card>
            <x-card>
                <h2 class="text-lg font-semibold">3 — Configuração inicial</h2>
                <p class="mt-2"><strong>Empresa:</strong> você pode cadastrar agora a razão social e os demais dados da empresa, ou fazer isso depois. Configure a empresa antes do primeiro produto.</p>
                <p class="mt-2"><strong>Seu acesso:</strong> decida se deseja continuar tendo acesso ao estabelecimento após a criação. A opção inicial é Não.</p>
                <p class="mt-2"><strong>Sim:</strong> permite auxiliar o cliente na configuração e no suporte. <strong>Não:</strong> o cliente fica sob administração da pessoa definida na etapa anterior, sem seu acesso operacional.</p>
                <p class="mt-2">Se você escolheu sua própria conta como administrador, precisa manter seu acesso ou escolher outra pessoa.</p>
            </x-card>
            <x-card>
                <h2 class="text-lg font-semibold">4 — Revisão</h2>
                <p class="mt-2">Confira as informações antes de criar o cliente: dados do estabelecimento, administrador, empresa e seu acesso para suporte.</p>
                <p class="mt-2">Você pode voltar para ajustar os campos. Nenhuma criação acontece antes da confirmação final no botão <strong>Criar cliente</strong>. A senha não aparece no resumo.</p>
            </x-card>
        </div>

        <x-card>
            <h2 class="text-lg font-semibold">Após a criação</h2>
            <p class="mt-2">O LucraOne prepara automaticamente a estrutura do estabelecimento, o acesso do administrador, as permissões necessárias e as configurações iniciais selecionadas.</p>
            <p class="mt-2">A tela de sucesso apresenta os próximos passos. Se você manteve seu acesso, os atalhos selecionam o novo estabelecimento antes de abrir a configuração. Caso contrário, oriente o administrador a entrar com sua própria conta e seguir o checklist no dashboard.</p>
            <h3 class="mt-4 font-semibold">Próximos passos</h3>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Configurar empresa, se ainda não cadastrada.</li>
                <li>Criar a primeira categoria.</li>
                <li>Cadastrar o primeiro produto.</li>
                <li>Definir preço.</li>
                <li>Informar estoque inicial.</li>
                <li>Cadastrar equipe: adicionar outra pessoa ativa além do administrador inicial.</li>
            </ol>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold">Exemplo prático</h2>
            <p class="mt-2">Exemplo fictício: cliente <strong>Massas do João</strong>; administrador <strong>João Silva</strong>, <span class="break-all">joao@exemplo.com.br</span>.</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li>Preencher Massas do João e o identificador massas-do-joao.</li>
                <li>Definir João como administrador usando seu e-mail.</li>
                <li>Decidir se configura a empresa agora.</li>
                <li>Decidir se o operador LucraOne mantém acesso para suporte.</li>
                <li>Revisar as informações.</li>
                <li>Confirmar em Criar cliente.</li>
            </ol>
        </x-card>

        <x-card>
            <h2 class="text-lg font-semibold">Dicas importantes</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Confira o e-mail do administrador antes de confirmar.</li>
                <li>Não compartilhe sua própria senha. Para uma pessoa nova, combine uma entrega segura da senha inicial.</li>
                <li>Se a pessoa já estiver cadastrada, use o mesmo e-mail.</li>
                <li>Configure corretamente o fuso horário.</li>
                <li>Você pode cadastrar a empresa posteriormente.</li>
                <li>Mantenha seu acesso quando houver necessidade de configuração ou suporte.</li>
            </ul>
            <p class="mt-4">Esta orientação continua disponível no dashboard e em Clientes do LucraOne.</p>
            <x-button href="{{ route('tenants.create') }}" class="mt-4">Cadastrar novo cliente</x-button>
        </x-card>
    </article>
</x-layouts.app>
