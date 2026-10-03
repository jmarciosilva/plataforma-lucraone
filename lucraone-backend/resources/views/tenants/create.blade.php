<x-layouts.app title="Novo cliente" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-sol"></span>
        criação guiada de um cliente do LucraOne
    </x-slot:subtitulo>
    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('tenants.index') }}">voltar para clientes</x-button>
    </x-slot:acoes>

    @php
        $passoInicial = 1;
        $camposEstabelecimento = ['name', 'slug', 'status', 'plan', 'timezone', 'locale', 'currency'];
        $camposAdministrador = ['administrator_email', 'administrator_name', 'password'];
        if ($errors->any() && ! $errors->hasAny($camposEstabelecimento)) {
            $passoInicial = $errors->hasAny($camposAdministrador) ? 2 : 3;
        }
        $inicial = ['passo' => $passoInicial, 'dados' => [
            'name' => old('name', ''), 'slug' => old('slug', ''),
            'status' => old('status', $tenant->status), 'plan' => old('plan', $tenant->plan),
            'timezone' => old('timezone', $tenant->timezone), 'locale' => old('locale', $tenant->locale),
            'currency' => old('currency', $tenant->currency),
            'administrator_name' => old('administrator_name', ''), 'administrator_email' => old('administrator_email', ''),
            'configure_company' => (string) old('configure_company', '0'),
            'keep_platform_access' => (string) old('keep_platform_access', '0'),
            'company_name' => old('company.legal_name', ''),
        ]];
        $situacoes = ['TRIAL' => 'Em teste', 'ACTIVE' => 'Ativo', 'SUSPENDED' => 'Suspenso', 'CANCELLED' => 'Encerrado'];
        $planos = ['free' => 'Gratuito', 'standard' => 'Padrão', 'enterprise' => 'Empresarial'];
        $idiomas = ['pt-BR' => 'Português (Brasil)', 'en-US' => 'Inglês (Estados Unidos)'];
    @endphp

    <x-card>
        <form method="POST" action="{{ route('tenants.onboarding.store') }}" novalidate
            x-data="onboardingCliente(@js($inicial), @js(route('tenants.onboarding.administrator')))"
            @submit="confirmar($event)" class="space-y-6">
            @csrf
            <noscript><p class="text-brasa">Ative o JavaScript para usar a criação guiada.</p></noscript>
            <p class="text-sm text-aco">Preencha os passos e confira o resumo. Nada será criado antes da confirmação final.</p>
            <ol class="grid grid-cols-1 gap-2 text-sm font-semibold sm:grid-cols-2 lg:grid-cols-4" aria-label="Passos da criação">
                @foreach (['Estabelecimento', 'Administrador', 'Configuração inicial', 'Revisão'] as $titulo)
                    <li class="rounded-xl border border-linha p-3" :class="passo === {{ $loop->iteration }} ? 'bg-nevoa text-sol' : 'text-aco'"
                        :aria-current="passo === {{ $loop->iteration }} ? 'step' : null">{{ $loop->iteration }} — {{ $titulo }}</li>
                @endforeach
            </ol>
            <h2 x-ref="titulo" tabindex="-1" class="text-lg font-semibold text-grafite">Passo <span x-text="passo">{{ $passoInicial }}</span> de 4</h2>
            @if ($errors->any())
                <p class="text-sm text-brasa" role="alert">Confira os campos destacados. Seus dados foram mantidos; por segurança, digite a senha novamente se a pessoa for nova.</p>
            @endif
            <p x-cloak x-show="aviso" x-text="aviso" class="text-sm text-brasa" role="alert"></p>

            <section x-ref="passo1" x-show="passo === 1" x-cloak class="space-y-4" aria-label="Estabelecimento">
                <p class="text-sm text-aco">O estabelecimento é o espaço do cliente no LucraOne, com seus próprios produtos, equipe e pedidos.</p>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-form-group nome="name" rotulo="Nome do estabelecimento" obrigatorio ajuda="Use o nome pelo qual o cliente reconhece seu negócio.">
                        <x-input nome="name" x-model="dados.name" required maxlength="255" autocomplete="organization" />
                    </x-form-group>
                    <x-form-group nome="slug" rotulo="Identificador no endereço" ajuda="Nome curto sem espaços, por exemplo: mercado-centro. Se vazio, será gerado a partir do nome.">
                        <x-input nome="slug" x-model="dados.slug" pattern="[a-zA-Z0-9_-]+" maxlength="255" />
                    </x-form-group>
                    <x-form-group nome="plan" rotulo="Plano" obrigatorio><x-select nome="plan" :opcoes="$planos" x-model="dados.plan" required /></x-form-group>
                    <x-form-group nome="status" rotulo="Situação" obrigatorio ajuda="Em teste e Ativo permitem operar. Suspenso e Encerrado impedem a operação."><x-select nome="status" :opcoes="$situacoes" x-model="dados.status" required /></x-form-group>
                    <x-form-group nome="timezone" rotulo="Fuso horário" obrigatorio ajuda="Usado para exibir datas e horários do negócio."><x-select nome="timezone" :opcoes="$timezoneOptions" x-model="dados.timezone" required /></x-form-group>
                    <x-form-group nome="locale" rotulo="Idioma" obrigatorio><x-select nome="locale" :opcoes="$idiomas" x-model="dados.locale" required /></x-form-group>
                    <x-form-group nome="currency" rotulo="Moeda" obrigatorio><x-select nome="currency" :opcoes="$moedaOptions" x-model="dados.currency" required /></x-form-group>
                </div>
            </section>

            <section x-ref="passo2" x-show="passo === 2" x-cloak class="space-y-4" aria-label="Administrador">
                <p class="text-sm text-aco">Quem será o administrador do cliente? Essa pessoa terá acesso à configuração e à equipe do estabelecimento. Sua conta de operador da plataforma não assume essa função automaticamente.</p>
                <x-form-group nome="administrator_email" rotulo="E-mail do administrador" obrigatorio ajuda="Informe o e-mail da pessoa responsável pelo cliente. Verificaremos se ela já possui conta.">
                    <x-input nome="administrator_email" tipo="email" x-ref="email" x-model="dados.administrator_email"
                        @input="identidade = null; emailConsultado = null" @blur="consultarAdministrador()" required maxlength="255" autocomplete="email" />
                </x-form-group>
                <p x-cloak x-show="consultando" role="status" class="text-sm text-aco">Verificando o e-mail…</p>
                <p x-cloak x-show="identidade?.exists && identidade?.available" class="rounded-xl bg-nevoa p-4 text-sm text-grafite">
                    Este e-mail já pertence a uma pessoa cadastrada no LucraOne. Ela será vinculada a este novo estabelecimento como administradora. Nome, senha e situação da conta serão mantidos.
                </p>
                <x-form-group nome="administrator_name" rotulo="Nome do administrador" ajuda="Para uma pessoa já cadastrada, mostramos o nome da conta existente.">
                    <x-input nome="administrator_name" x-model="dados.administrator_name" x-bind:readonly="identidade?.exists"
                        x-bind:required="!identidade?.exists" maxlength="255" autocomplete="name" />
                </x-form-group>
                <div x-show="!identidade?.exists" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-form-group nome="password" rotulo="Senha inicial" ajuda="Mínimo de 8 caracteres. Combine uma entrega segura com o administrador; a senha não será mostrada após a criação.">
                        <x-input nome="password" tipo="password" x-ref="password" x-bind:disabled="identidade?.exists"
                            x-bind:required="!identidade?.exists" minlength="8" autocomplete="new-password" />
                    </x-form-group>
                    <x-form-group nome="password_confirmation" rotulo="Confirme a senha inicial">
                        <x-input nome="password_confirmation" tipo="password" x-ref="confirmation" x-bind:disabled="identidade?.exists"
                            x-bind:required="!identidade?.exists" minlength="8" autocomplete="new-password" />
                    </x-form-group>
                </div>
            </section>

            <section x-ref="passo3" x-show="passo === 3" x-cloak class="space-y-4" aria-label="Configuração inicial">
                <x-form-group nome="configure_company" rotulo="Configurar empresa agora?" ajuda="Empresa reúne a razão social e os dados fiscais já usados no cadastro. Pode ficar para depois, mas será necessária antes do primeiro produto.">
                    <x-select nome="configure_company" :opcoes="['0' => 'Não, configurar depois', '1' => 'Sim, configurar agora']" x-model="dados.configure_company" />
                </x-form-group>
                <fieldset x-show="dados.configure_company === '1'" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <legend class="mb-3 text-sm font-semibold">Dados da empresa</legend>
                    <x-form-group nome="company.legal_name" rotulo="Razão social" obrigatorio>
                        <x-input nome="company[legal_name]" id="company.legal_name" :erro="$errors->has('company.legal_name')" x-model="dados.company_name"
                            x-bind:disabled="dados.configure_company !== '1'" x-bind:required="dados.configure_company === '1'" maxlength="255" />
                    </x-form-group>
                    @foreach (['trade_name' => 'Nome fantasia', 'document' => 'Documento (CNPJ)', 'state_registration' => 'Inscrição estadual', 'municipal_registration' => 'Inscrição municipal', 'email' => 'E-mail da empresa', 'phone' => 'Telefone'] as $campo => $rotulo)
                        <x-form-group :nome="'company.'.$campo" :rotulo="$rotulo">
                            <x-input :nome="'company['.$campo.']'" :id="'company.'.$campo" :valor="old('company.'.$campo)" :erro="$errors->has('company.'.$campo)"
                                :tipo="$campo === 'email' ? 'email' : 'text'" x-bind:disabled="dados.configure_company !== '1'"
                                :maxlength="in_array($campo, ['document', 'phone']) ? 32 : (str_contains($campo, 'registration') ? 64 : 255)" />
                        </x-form-group>
                    @endforeach
                    <x-form-group nome="company.status" rotulo="Situação da empresa" obrigatorio>
                        <x-select nome="company[status]" id="company.status" :opcoes="['ACTIVE' => 'Ativa', 'INACTIVE' => 'Inativa', 'SUSPENDED' => 'Suspensa']"
                            :valor="old('company.status', 'ACTIVE')" x-bind:disabled="dados.configure_company !== '1'" />
                    </x-form-group>
                </fieldset>
                <x-form-group nome="keep_platform_access" rotulo="Manter meu acesso a este estabelecimento após a criação?" ajuda="Sim: você terá acesso administrativo local para auxiliar configuração e suporte. Não: sua conta continuará como operadora da plataforma, sem vínculo com este estabelecimento.">
                    <x-select nome="keep_platform_access" :opcoes="['0' => 'Não, somente o administrador do cliente', '1' => 'Sim, manter meu acesso para suporte']" x-model="dados.keep_platform_access" />
                </x-form-group>
            </section>

            <section x-show="passo === 4" x-cloak class="space-y-4" aria-label="Revisão">
                <p class="text-sm text-aco">Confira quem receberá acesso e as configurações. A senha não aparece neste resumo.</p>
                <dl class="grid grid-cols-1 gap-4 rounded-xl bg-nevoa p-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-aco">Estabelecimento</dt><dd x-text="dados.name"></dd></div>
                    <div><dt class="text-xs text-aco">Identificador no endereço</dt><dd x-text="dados.slug || 'Gerado a partir do nome'"></dd></div>
                    <div><dt class="text-xs text-aco">Plano</dt><dd x-text="{{ Js::from($planos) }}[dados.plan]"></dd></div>
                    <div><dt class="text-xs text-aco">Situação</dt><dd x-text="{{ Js::from($situacoes) }}[dados.status]"></dd></div>
                    <div><dt class="text-xs text-aco">Fuso horário</dt><dd x-text="dados.timezone"></dd></div>
                    <div><dt class="text-xs text-aco">Idioma e moeda</dt><dd><span x-text="{{ Js::from($idiomas) }}[dados.locale]"></span> · <span x-text="dados.currency"></span></dd></div>
                    <div><dt class="text-xs text-aco">Administrador</dt><dd><span x-text="dados.administrator_name"></span> · <span x-text="dados.administrator_email"></span></dd></div>
                    <div><dt class="text-xs text-aco">Conta do administrador</dt><dd x-text="identidade?.exists ? 'Pessoa já cadastrada — dados globais mantidos' : 'Nova pessoa — conta será criada'"></dd></div>
                    <div><dt class="text-xs text-aco">Empresa</dt><dd x-text="dados.configure_company === '1' ? 'Será criada: ' + dados.company_name : 'Não será criada agora'"></dd></div>
                    <div><dt class="text-xs text-aco">Meu acesso para suporte</dt><dd x-text="dados.keep_platform_access === '1' ? 'Sim, com acesso administrativo local' : 'Não, sem vínculo local'"></dd></div>
                </dl>
            </section>

            <div class="flex flex-wrap justify-end gap-2 border-t border-linha pt-5">
                <x-button variante="secundario" href="{{ route('tenants.index') }}">cancelar</x-button>
                <x-button variante="secundario" x-show="passo > 1" x-cloak @click="voltar()" x-bind:disabled="enviando">voltar um passo</x-button>
                <x-button x-show="passo < 4" @click="avancar()" x-bind:disabled="consultando">avançar</x-button>
                <x-button tipo="submit" x-show="passo === 4" x-cloak x-bind:disabled="enviando">Criar cliente</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.app>
