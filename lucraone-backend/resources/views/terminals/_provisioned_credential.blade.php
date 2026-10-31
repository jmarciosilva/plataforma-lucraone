{{--
    Exibe a credencial de máquina (token Bearer) APENAS uma vez, na resposta
    imediata ao provisionamento. O token não é salvo em texto puro — o banco
    guarda apenas o SHA-256 (Sanctum). Depois deste request, o valor some da
    sessão flash e não há como relê-lo.

    Variáveis esperadas: $token, $tipo, $expira, $terminal.
--}}
<div x-data="{
        aberto: true,
        copiado: false,
        copiar() {
            const campo = $refs.campo;
            campo.select();
            campo.setSelectionRange(0, 99999);
            const feito = () => { this.copiado = true; setTimeout(() => this.copiado = false, 2500); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(campo.value).then(feito).catch(() => { try { document.execCommand('copy'); feito(); } catch (e) {} });
            } else {
                try { document.execCommand('copy'); feito(); } catch (e) {}
            }
        }
     }"
     x-show="aberto" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-grafite/40 p-4 backdrop-blur-sm">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.stop>
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold lowercase text-grafite">credencial do terminal</h2>
            <button type="button" @click="aberto = false" aria-label="fechar" class="text-aco transition-colors hover:text-grafite">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="mt-4 flex items-start gap-3 rounded-xl border border-alerta/30 bg-alerta/5 px-4 py-3">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 text-alerta" aria-hidden="true">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                <path d="M12 9v4M12 17h.01" />
            </svg>
            <p class="text-sm font-semibold text-alerta">Guarde este token agora. Ele não será exibido novamente.</p>
        </div>

        <label for="credencial-token" class="mt-5 block text-sm font-semibold lowercase text-grafite">token de acesso ({{ $tipo }})</label>
        <div class="mt-1.5 flex items-stretch gap-2">
            <input id="credencial-token" x-ref="campo" type="text" readonly
                   value="{{ $token }}"
                   class="block w-full min-h-11 select-all break-all rounded-xl border border-linha bg-nevoa px-3 font-comanda text-sm text-grafite focus:outline-none focus:ring-2 focus:ring-sol/30" />
            <button type="button" @click="copiar()"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-linha px-4 text-sm font-semibold lowercase text-grafite transition-colors hover:bg-nevoa"
                    x-text="copiado ? 'copiado' : 'copiar'"></button>
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div class="rounded-xl bg-nevoa px-3 py-2">
                <dt class="text-xs lowercase text-aco">terminal</dt>
                <dd class="font-semibold text-grafite">{{ $terminal->name }}</dd>
            </div>
            <div class="rounded-xl bg-nevoa px-3 py-2">
                <dt class="text-xs lowercase text-aco">filial</dt>
                <dd class="font-semibold text-grafite">{{ $terminal->branch?->name ?? '—' }}</dd>
            </div>
            <div class="rounded-xl bg-nevoa px-3 py-2 sm:col-span-2">
                <dt class="text-xs lowercase text-aco">a credencial expira em</dt>
                <dd class="font-semibold text-grafite">{{ $expira }}</dd>
            </div>
        </dl>

        <p class="mt-4 text-xs leading-5 text-aco">
            Insira este token na configuração do PDV (bootstrap.properties ou tela de setup).
            A partir daí o aplicativo autentica-se automaticamente, sem pareamento. Antes do
            vencimento, o próprio PDV renova a credencial.
        </p>

        <div class="mt-6 flex justify-end">
            <x-button @click="aberto = false">fechar</x-button>
        </div>
    </div>
</div>
