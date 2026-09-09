@php
    /*
    | Formulário de regra.
    |
    | O catálogo de gatilhos vai para o Alpine para que a troca de gatilho
    | atualize campos e operadores sem ida ao servidor. O servidor revalida
    | tudo mesmo assim — o JavaScript aqui é conveniência, não a trava.
    */
    $condicoesAtuais = old('conditions', $regra->conditions ?? []);

    if (empty($condicoesAtuais)) {
        $condicoesAtuais = [['campo' => '', 'operador' => '', 'valor' => '']];
    }

    $configAtual = old('action_config', $regra->action_config ?? []);
@endphp

<div
    x-data="{
        gatilho: @js(old('trigger', $regra->trigger)),
        acao: @js(old('action', $regra->action)),
        catalogo: @js($catalogo),
        operadores: @js($operadoresPorTipo),
        gatilhosComProduto: @js($acoesComProduto),
        condicoes: @js(array_values($condicoesAtuais)),

        get campos() {
            return this.catalogo[this.gatilho]?.campos ?? [];
        },
        get descricao() {
            return this.catalogo[this.gatilho]?.descricao ?? '';
        },
        tipoDoCampo(chave) {
            return this.campos.find(c => c.chave === chave)?.tipo ?? 'texto';
        },
        operadoresDoCampo(chave) {
            if (! chave) return {};
            return this.operadores[this.tipoDoCampo(chave)] ?? {};
        },
        get acaoIncompativel() {
            return this.acao === 'update_price' && ! this.gatilhosComProduto.includes(this.gatilho);
        },
        adicionarCondicao() {
            this.condicoes.push({ campo: '', operador: '', valor: '' });
        },
        removerCondicao(indice) {
            this.condicoes.splice(indice, 1);
            if (this.condicoes.length === 0) this.adicionarCondicao();
        },
        trocarGatilho() {
            // Campos do gatilho antigo não existem no novo: limpa em vez de
            // deixar o operador salvar uma condição que o servidor recusaria.
            this.condicoes = [{ campo: '', operador: '', valor: '' }];
        },
    }"
    class="space-y-8"
>
    <div>
        <x-section-label>identificação</x-section-label>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-form-group nome="name" rotulo="nome da regra" obrigatorio class="lg:col-span-2">
                <x-input nome="name" :valor="$regra->name" placeholder="avisar quando o arroz acabar" />
            </x-form-group>

            <x-form-group nome="active" rotulo="situação">
                <x-select
                    nome="active"
                    :opcoes="['1' => 'ativa', '0' => 'inativa']"
                    :valor="old('active', $regra->active === false ? '0' : '1')"
                />
            </x-form-group>
        </div>

        <x-form-group nome="description" rotulo="descrição" class="mt-4">
            <x-input tipo="textarea" nome="description" :valor="$regra->description" rows="2"
                placeholder="para que serve esta regra" />
        </x-form-group>
    </div>

    <div>
        <x-section-label>quando (gatilho)</x-section-label>

        <x-card>
            <x-form-group nome="trigger" rotulo="disparar quando" obrigatorio>
                <x-select nome="trigger" :opcoes="$gatilhos" :valor="old('trigger', $regra->trigger)"
                    x-model="gatilho" @change="trocarGatilho()" />
            </x-form-group>

            <p class="mt-2 text-xs text-aco" x-text="descricao"></p>
        </x-card>
    </div>

    <div>
        <x-section-label>se (condições)</x-section-label>

        <x-card>
            <p class="text-xs text-aco">
                todas as condições precisam ser verdadeiras. deixe em branco para a regra valer sempre que o gatilho ocorrer.
            </p>

            <div class="mt-4 space-y-3">
                <template x-for="(condicao, indice) in condicoes" :key="indice">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <select
                                :name="`conditions[${indice}][campo]`"
                                x-model="condicao.campo"
                                @change="condicao.operador = ''"
                                class="block min-h-11 w-full rounded-xl border border-linha bg-white px-3 text-sm text-grafite focus:border-sol focus:outline-none focus:ring-2 focus:ring-sol/30"
                            >
                                <option value="">campo…</option>
                                <template x-for="campo in campos" :key="campo.chave">
                                    <option :value="campo.chave" x-text="campo.rotulo"></option>
                                </template>
                            </select>
                        </div>

                        <div class="sm:col-span-4">
                            <select
                                :name="`conditions[${indice}][operador]`"
                                x-model="condicao.operador"
                                :disabled="! condicao.campo"
                                class="block min-h-11 w-full rounded-xl border border-linha bg-white px-3 text-sm text-grafite disabled:opacity-50 focus:border-sol focus:outline-none focus:ring-2 focus:ring-sol/30"
                            >
                                <option value="">condição…</option>
                                <template x-for="(rotulo, chave) in operadoresDoCampo(condicao.campo)" :key="chave">
                                    <option :value="chave" x-text="rotulo"></option>
                                </template>
                            </select>
                        </div>

                        <div class="sm:col-span-3">
                            <input
                                :name="`conditions[${indice}][valor]`"
                                x-model="condicao.valor"
                                :type="tipoDoCampo(condicao.campo) === 'numero' ? 'number' : 'text'"
                                step="any"
                                placeholder="valor"
                                class="block min-h-11 w-full rounded-xl border border-linha bg-white px-3 text-sm text-grafite placeholder:text-aco focus:border-sol focus:outline-none focus:ring-2 focus:ring-sol/30"
                            >
                        </div>

                        <div class="flex items-center sm:col-span-1">
                            <button type="button" @click="removerCondicao(indice)"
                                class="flex h-11 w-11 items-center justify-center rounded-xl text-aco transition-colors hover:bg-nevoa hover:text-brasa"
                                aria-label="remover condição">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="h-4 w-4">
                                    <path d="M18 6L6 18M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            @error('conditions')
                <p class="mt-2 text-xs font-semibold text-brasa">{{ $message }}</p>
            @enderror
            @foreach ($errors->get('conditions.*') as $mensagens)
                @foreach ($mensagens as $mensagem)
                    <p class="mt-2 text-xs font-semibold text-brasa">{{ $mensagem }}</p>
                @endforeach
            @endforeach

            <x-button variante="secundario" tipo="button" class="mt-4" @click="adicionarCondicao()">
                adicionar condição
            </x-button>
        </x-card>
    </div>

    <div>
        <x-section-label>então (ação)</x-section-label>

        <x-card>
            <x-form-group nome="action" rotulo="fazer o quê" obrigatorio>
                <x-select nome="action" :opcoes="$acoes" :valor="old('action', $regra->action)" x-model="acao" />
            </x-form-group>

            <template x-if="acaoIncompativel">
                <p class="mt-2 flex items-center gap-1 text-xs font-semibold text-brasa">
                    esta ação só funciona com gatilhos que carregam um produto.
                </p>
            </template>

            {{--
                Cada bloco usa x-if, não x-show: os três compartilham nomes de
                campo (action_config[message], por exemplo). Escondido com
                x-show o campo continua sendo enviado, e o vazio do bloco
                inativo sobrescreveria o preenchido. x-if tira do DOM.
            --}}
            <template x-if="acao === 'create_notification'">
            <div class="mt-4 space-y-4">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-form-group nome="action_config.title" rotulo="título do aviso" class="lg:col-span-2">
                        <x-input nome="action_config[title]" :valor="$configAtual['title'] ?? ''"
                            placeholder="estoque baixo: {produto}" />
                    </x-form-group>

                    <x-form-group nome="action_config.level" rotulo="nível">
                        <x-select nome="action_config[level]" :opcoes="$niveis"
                            :valor="$configAtual['level'] ?? 'atencao'" />
                    </x-form-group>
                </div>

                <x-form-group nome="action_config.message" rotulo="mensagem"
                    ajuda="use {campo} para inserir dados do gatilho">
                    <x-input tipo="textarea" nome="action_config[message]" :valor="$configAtual['message'] ?? ''"
                        rows="2" placeholder="restam {quantidade} unidades de {produto}." />
                </x-form-group>
            </div>
            </template>

            <template x-if="acao === 'send_email'">
            <div class="mt-4 space-y-4">
                <x-form-group nome="action_config.recipients" rotulo="destinatários"
                    ajuda="separe por vírgula">
                    <x-input nome="action_config[recipients]" :valor="$configAtual['recipients'] ?? ''"
                        placeholder="compras@empresa.com, gerencia@empresa.com" />
                </x-form-group>

                <x-form-group nome="action_config.subject" rotulo="assunto">
                    <x-input nome="action_config[subject]" :valor="$configAtual['subject'] ?? ''"
                        placeholder="estoque baixo: {produto}" />
                </x-form-group>

                <x-form-group nome="action_config.message" rotulo="mensagem"
                    ajuda="use {campo} para inserir dados do gatilho">
                    <x-input tipo="textarea" nome="action_config[message]" :valor="$configAtual['message'] ?? ''"
                        rows="2" />
                </x-form-group>
            </div>
            </template>

            <template x-if="acao === 'update_price'">
            <div class="mt-4 space-y-4">
                <x-alert tipo="atencao">
                    esta ação muda o preço do produto sozinha. toda alteração fica no histórico de preços,
                    e o ajuste percentual é limitado a {{ $limiteDeVariacao }}% por execução.
                </x-alert>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <x-form-group nome="action_config.price_type" rotulo="qual preço">
                        <x-select nome="action_config[price_type]" :opcoes="$tiposDePreco"
                            :valor="$configAtual['price_type'] ?? 'sale'" />
                    </x-form-group>

                    <x-form-group nome="action_config.operation" rotulo="como ajustar">
                        <x-select nome="action_config[operation]" :opcoes="$operacoesDePreco"
                            :valor="$configAtual['operation'] ?? 'percentual'" />
                    </x-form-group>

                    <x-form-group nome="action_config.amount" rotulo="valor"
                        ajuda="percentual aceita negativo">
                        <x-input tipo="number" nome="action_config[amount]" step="0.01"
                            :valor="$configAtual['amount'] ?? ''" />
                    </x-form-group>
                </div>
            </div>
        </x-card>
    </div>
</div>
