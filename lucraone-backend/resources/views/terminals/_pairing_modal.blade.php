{{--
    Exibe o código de pareamento APENAS uma vez, na resposta imediata ao POST.
    O código é sensível: não é salvo no banco em texto puro, apenas o hash.
    Depois deste redirect, o valor some da sessão flash.
--}}
<div x-data="{ aberto: true }" x-show="aberto" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-grafite/40 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @click.stop>
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold lowercase text-grafite">código de pareamento</h2>
            <button type="button" @click="aberto = false" class="text-aco hover:text-grafite transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <p class="mt-2 text-sm text-aco">
            Informe este código ao operador de caixa. Ele tem validade única e expira em
            <strong class="text-grafite">{{ $expira->format('H:i \d\e d/m/Y') }}</strong>.
        </p>

        <div class="mt-4 rounded-xl bg-nevoa p-4 text-center">
            <p class="font-comanda text-2xl uppercase tracking-widest text-grafite select-all">{{ $codigo }}</p>
        </div>

        <p class="mt-3 text-xs text-aco">
            Após o pareamento bem-sucedido, o código é consumido e não pode ser reusado.
            O terminal passará para o estado <strong>ativo</strong> automaticamente.
        </p>

        <div class="mt-6 flex justify-end">
            <x-button @click="aberto = false">fechar</x-button>
        </div>
    </div>
</div>
