@props(['texto'])

{{--
    Dica curta num ícone "?". Acessível: é um botão (foco por teclado), abre no
    hover e no foco, fecha ao sair/desfocar, e alterna no clique (toque). O
    balão reseta o caixa/rastreio do rótulo em que vive, para ler como frase.
--}}
<span x-data="{ aberto: false }" class="relative inline-flex align-middle">
    <button
        type="button"
        @mouseenter="aberto = true"
        @mouseleave="aberto = false"
        @focus="aberto = true"
        @blur="aberto = false"
        @click.prevent="aberto = ! aberto"
        :aria-expanded="aberto ? 'true' : 'false'"
        class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-linha text-[0.6rem] font-bold normal-case tracking-normal text-aco transition-colors hover:border-sol hover:text-sol focus:outline-none focus-visible:ring-2 focus-visible:ring-sol/40"
        aria-label="o que é isto?"
    >?</button>

    <span
        x-show="aberto"
        x-cloak
        x-transition.opacity
        role="tooltip"
        class="absolute left-0 top-full z-20 mt-2 w-56 rounded-lg bg-grafite px-3 py-2 text-xs font-normal normal-case leading-snug tracking-normal text-white shadow-cartao"
    >{{ $texto }}</span>
</span>
