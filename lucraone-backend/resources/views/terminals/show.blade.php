<x-layouts.app title="terminais pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        @php
            $cor = match($terminal->status) {
                'ACTIVE' => 'bg-ok',
                'BLOCKED' => 'bg-brasa',
                'REVOKED' => 'bg-aco',
                default => 'bg-sol',
            };
        @endphp
        <span class="h-2 w-2 rounded-full {{ $cor }}"></span>
        detalhe do terminal
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('terminals.index') }}">voltar</x-button>
        @if (config('pdv_catalog.enabled', false) && $terminal->company_id && auth()->user()->hasPermission('manage-products', $terminal->tenant_id))
            <x-button variante="secundario" href="{{ route('pdv.catalog-loads.index', ['company_id' => $terminal->company_id, 'terminal_id' => $terminal->id]) }}">cargas deste terminal</x-button>
        @endif
        @if ($podeAdministrar && $terminal->status !== 'REVOKED')
            <x-button href="{{ route('terminals.edit', $terminal) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    @if (session('sucesso'))
        <x-alert tipo="sucesso" class="mb-6">{{ session('sucesso') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert tipo="erro" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    @if (session('codigoPareamento'))
        @include('terminals._pairing_modal', [
            'codigo' => session('codigoPareamento'),
            'expira' => session('codigoExpira'),
        ])
    @endif

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="situação" :valor="match($terminal->status) {
            'PENDING' => 'aguardando',
            'ACTIVE' => 'ativo',
            'BLOCKED' => 'bloqueado',
            'REVOKED' => 'revogado',
            default => strtolower($terminal->status),
        }" nota="do terminal" />
        <x-kpi rotulo="filial" :valor="$terminal->branch?->name ?? '—'" nota="vinculação" />
        <x-kpi rotulo="último acesso" :valor="$terminal->last_seen_at?->format('d/m H:i') ?? '—'" nota="heartbeat" />
        <x-kpi rotulo="cadastrado" :valor="$terminal->created_at->format('d/m/Y')" nota="criação" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $terminal->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">id</dt>
                    <dd class="mt-1 font-comanda text-xs uppercase tracking-wider text-grafite">{{ $terminal->id }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">instalação</dt>
                    <dd class="mt-1 font-comanda text-xs uppercase tracking-wider text-grafite">{{ $terminal->installation_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">empresa</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $terminal->branch?->company?->legal_name ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($podeRecuperarCredencial)
                <div class="mt-4" x-data="recuperacaoTerminal(@js(route('terminals.credential-recovery', $terminal)), @js(['name' => $terminal->name, 'company' => $terminal->company?->trade_name ?? $terminal->company?->legal_name, 'branch' => $terminal->branch?->name, 'status' => 'Ativo', 'installation' => $terminal->installation_id]), @js(csrf_token()))">
                    <button type="button" @click="open()"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-sol px-4 text-sm font-semibold lowercase text-grafite transition-opacity hover:opacity-90">
                        gerar código de recuperação
                    </button>

                    <div x-cloak x-show="modal" @keydown.escape.window="if (!busy) close()"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-grafite/50 p-4 backdrop-blur-sm">
                        <section role="dialog" aria-modal="true" aria-labelledby="recovery-title"
                                 class="max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" @click.stop>
                            <template x-if="stage === 'confirm'">
                                <div>
                                    <h2 id="recovery-title" class="text-lg font-semibold lowercase text-grafite">recuperar credencial do terminal</h2>
                                    <dl class="mt-4 grid grid-cols-1 gap-3 rounded-xl bg-nevoa p-4 text-sm sm:grid-cols-2">
                                        <div><dt class="text-xs text-aco">terminal</dt><dd class="font-semibold text-grafite" x-text="terminal.name"></dd></div>
                                        <div><dt class="text-xs text-aco">situação</dt><dd class="font-semibold text-grafite" x-text="terminal.status"></dd></div>
                                        <div><dt class="text-xs text-aco">empresa</dt><dd class="text-grafite" x-text="terminal.company || '—'"></dd></div>
                                        <div><dt class="text-xs text-aco">filial</dt><dd class="text-grafite" x-text="terminal.branch || '—'"></dd></div>
                                        <div class="sm:col-span-2"><dt class="text-xs text-aco">instalação</dt><dd class="break-all font-comanda text-xs text-grafite" x-text="terminal.installation"></dd></div>
                                    </dl>
                                    <p class="mt-4 text-sm leading-6 text-aco">Esta operação emitirá um código temporário para que o computador recupere sua credencial de acesso ao LucraOne. As credenciais de máquina anteriores serão invalidadas. O terminal e sua instalação continuarão cadastrados.</p>

                                    <label class="mt-5 block text-sm font-semibold text-grafite" for="recovery-reason">motivo da recuperação</label>
                                    <select id="recovery-reason" x-model="reason" class="mt-2 min-h-11 w-full rounded-xl border border-linha bg-white px-3 text-sm text-grafite focus:border-sol focus:outline-none focus:ring-2 focus:ring-sol/30">
                                        <option value="">selecione um motivo</option>
                                        <option value="expired">credencial expirada</option>
                                        <option value="lost">credencial perdida ou indisponível</option>
                                        <option value="compromised">suspeita de comprometimento</option>
                                        <option value="keyring-unavailable">armazenamento seguro indisponível</option>
                                        <option value="rotation-failed">falha de renovação da credencial</option>
                                    </select>
                                    <label class="mt-4 flex gap-3 text-sm leading-5 text-aco">
                                        <input type="checkbox" x-model="confirmed" class="mt-1 rounded border-linha text-sol focus:ring-sol">
                                        <span>Confirmo que desejo invalidar as credenciais anteriores e gerar um novo código de recuperação.</span>
                                    </label>
                                    <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-brasa"></p>
                                    <div class="mt-6 flex flex-col-reverse justify-end gap-2 sm:flex-row">
                                        <button type="button" @click="close()" :disabled="busy" class="min-h-11 rounded-xl border border-linha px-4 text-sm font-semibold text-grafite disabled:opacity-50">cancelar</button>
                                        <button type="button" @click="issue()" :disabled="busy || !reason || !confirmed" class="min-h-11 rounded-xl bg-sol px-4 text-sm font-semibold text-grafite disabled:cursor-not-allowed disabled:opacity-50">
                                            <span x-text="busy ? 'processando…' : 'confirmar e gerar código'"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="stage === 'result'">
                                <div>
                                    <h2 id="recovery-title" class="text-lg font-semibold lowercase text-grafite">código de recuperação gerado</h2>
                                    <p class="mt-3 text-sm text-aco">Terminal <strong class="text-grafite" x-text="terminal.name"></strong>. O código expira em <strong class="text-grafite" x-text="expiryLabel"></strong> e pode ser usado uma única vez.</p>
                                    <div class="mt-5 rounded-xl border border-sol/40 bg-sol/10 p-4 text-center">
                                        <code x-text="code" class="block select-all break-all font-comanda text-2xl tracking-widest text-grafite" aria-live="polite"></code>
                                    </div>
                                    <button type="button" @click="copyCode()" class="mt-3 min-h-11 w-full rounded-xl border border-linha px-4 text-sm font-semibold text-grafite" x-text="copied ? 'código copiado' : 'copiar código'"></button>
                                    <h3 class="mt-6 text-sm font-semibold text-grafite">No aplicativo Java</h3>
                                    <ol class="mt-2 list-inside list-decimal space-y-1 text-sm leading-6 text-aco">
                                        <li>Mantenha o LucraOne PDV aberto em “Terminal sem autorização”.</li>
                                        <li>Digite o código no campo “Código de recuperação fornecido pelo administrador”.</li>
                                        <li>Clique em “Recuperar credencial” e aguarde a confirmação.</li>
                                        <li>Após a validação, o aplicativo retornará à identificação do operador.</li>
                                    </ol>
                                    <p x-show="error" x-text="error" role="alert" class="mt-3 text-sm text-brasa"></p>
                                    <div class="mt-6 flex justify-end">
                                        <button type="button" @click="close()" class="min-h-11 rounded-xl bg-sol px-5 text-sm font-semibold text-grafite">fechar e apagar código</button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="stage === 'uncertain'">
                                <div>
                                    <h2 id="recovery-title" class="text-lg font-semibold lowercase text-grafite">resultado não confirmado</h2>
                                    <p class="mt-3 text-sm leading-6 text-aco">O navegador não recebeu uma confirmação confiável. A emissão pode ter sido concluída e invalidado as credenciais anteriores. Verifique o estado da operação com o suporte antes de solicitar outro código. Não repetimos a emissão automaticamente.</p>
                                    <div class="mt-6 flex justify-end">
                                        <button type="button" @click="close()" class="min-h-11 rounded-xl bg-sol px-5 text-sm font-semibold text-grafite">fechar</button>
                                    </div>
                                </div>
                            </template>
                        </section>
                    </div>
                </div>
            @elseif ($terminal->status === 'BLOCKED' && $terminal->installation_id)
                <p class="mt-4 rounded-xl bg-nevoa p-3 text-sm leading-5 text-aco">Desbloqueie administrativamente o terminal antes de iniciar a recuperação da credencial.</p>
            @endif

            @if ($podeAdministrar)
                @if ($terminal->status === 'PENDING')
                    <form method="POST" action="{{ route('terminals.pairing-code', $terminal) }}" class="mt-4">
                        @csrf
                        <x-button tipo="submit" class="w-full">gerar código de pareamento</x-button>
                    </form>

                    <form method="POST" action="{{ route('terminals.revoke', $terminal) }}" class="mt-2"
                          onsubmit="return confirm('Revogar este terminal? Esta ação é irreversível.')">
                        @csrf
                        <x-button variante="perigo" tipo="submit" class="w-full">revogar</x-button>
                    </form>
                @elseif ($terminal->status === 'ACTIVE')
                    <form method="POST" action="{{ route('terminals.pairing-code', $terminal) }}" class="mt-4">
                        @csrf
                        <x-button variante="secundario" tipo="submit" class="w-full">novo código de pareamento</x-button>
                    </form>

                    <button type="button" x-data @click="$dispatch('abrir-modal', 'bloquear-terminal')"
                        class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90">
                        bloquear
                    </button>

                    <x-modal nome="bloquear-terminal" titulo="bloquear terminal">
                        <p class="text-sm text-aco">O terminal será bloqueado imediatamente. O aplicativo deixará de autenticar até o desbloqueio.</p>
                        <x-slot:acoes>
                            <form method="POST" action="{{ route('terminals.block', $terminal) }}">
                                @csrf
                                <x-button variante="perigo" tipo="submit">confirmar bloqueio</x-button>
                            </form>
                        </x-slot:acoes>
                    </x-modal>

                    <form method="POST" action="{{ route('terminals.revoke', $terminal) }}" class="mt-2"
                          onsubmit="return confirm('Revogar este terminal? Esta ação é irreversível.')">
                        @csrf
                        <x-button variante="perigo" tipo="submit" class="w-full">revogar</x-button>
                    </form>
                @elseif ($terminal->status === 'BLOCKED')
                    <form method="POST" action="{{ route('terminals.unblock', $terminal) }}" class="mt-4">
                        @csrf
                        <x-button tipo="submit" class="w-full">desbloquear</x-button>
                    </form>

                    <form method="POST" action="{{ route('terminals.revoke', $terminal) }}" class="mt-2"
                          onsubmit="return confirm('Revogar este terminal? Esta ação é irreversível.')">
                        @csrf
                        <x-button variante="perigo" tipo="submit" class="w-full">revogar</x-button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-aco">Terminal revogado. Nenhuma ação disponível.</p>
                @endif
            @endif
        </x-card>
    </div>

    @if ($codigoPendente)
        <x-section-label class="mt-8">código de pareamento ativo</x-section-label>
        <x-card>
            <p class="text-sm text-aco">
                Existe um código de pareamento válido até
                <strong class="text-grafite">{{ $codigoPendente->expires_at->format('d/m/Y H:i') }}</strong>.
                Gere um novo para invalidar este.
            </p>
        </x-card>
    @endif

    @if ($historico->isNotEmpty())
        <x-section-label class="mt-8">histórico</x-section-label>

        <x-card>
            <ul class="divide-y divide-linha">
                @foreach ($historico as $evento)
                    <li class="py-3 first:pt-0 last:pb-0">
                        <p class="text-sm text-grafite">{{ $evento->description }}</p>
                        <p class="mt-0.5 font-comanda text-[0.65rem] uppercase tracking-wider text-aco">
                            {{ $evento->action }} · {{ $evento->created_at->format('d/m/Y H:i') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-layouts.app>
