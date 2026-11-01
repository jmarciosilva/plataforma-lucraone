<x-layouts.app title="restaurante" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full border border-aco/30 bg-nevoa"></span>
        {{ $filialNome }} · em configuração
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('dashboard') }}">atualizar</x-button>
    </x-slot:acoes>

    {{-- Aviso de configuração --}}
    <div class="mb-8 rounded-xl border border-linha bg-nevoa px-5 py-4">
        <p class="text-sm font-semibold lowercase text-grafite">módulo de restaurante em configuração</p>
        <p class="mt-2 text-sm leading-relaxed text-aco">
            As funcionalidades operacionais do restaurante — cardápio digital, mesas, comandas e cozinha —
            estão sendo desenvolvidas. Use os itens do menu lateral para configurar
            produtos, estoque, usuários e demais recursos disponíveis.
        </p>
    </div>

    {{-- Ações disponíveis agora --}}
    <x-section-label>configure agora</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <a href="{{ route('catalog.products.index') }}" class="cartao p-5 transition-colors hover:bg-nevoa">
            <p class="text-sm font-semibold lowercase text-grafite">produtos</p>
            <p class="mt-2 text-sm text-aco">
                Cadastre os itens do cardápio — pratos, bebidas e complementos.
                Os produtos cadastrados aqui serão usados nas comandas quando o módulo for liberado.
            </p>
        </a>

        <a href="{{ route('inventory.index') }}" class="cartao p-5 transition-colors hover:bg-nevoa">
            <p class="text-sm font-semibold lowercase text-grafite">estoque</p>
            <p class="mt-2 text-sm text-aco">
                Gerencie o estoque de ingredientes e insumos.
                O controle de estoque funcionará de forma integrada com as comandas.
            </p>
        </a>

        <a href="{{ route('users.index') }}" class="cartao p-5 transition-colors hover:bg-nevoa">
            <p class="text-sm font-semibold lowercase text-grafite">usuários</p>
            <p class="mt-2 text-sm text-aco">
                Cadastre garçons, cozinheiros e gerentes.
                Cada pessoa precisará de acesso antes da operação começar.
            </p>
        </a>
    </div>

    {{-- Funcionalidades em desenvolvimento --}}
    <x-section-label class="mt-8">em desenvolvimento</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-card class="p-5 opacity-60">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-linha px-2 py-0.5">
                <span class="h-1.5 w-1.5 rounded-full bg-aco/50"></span>
                <span class="font-comanda text-[0.6rem] uppercase tracking-widest text-aco">em desenvolvimento</span>
            </div>
            <p class="text-sm font-semibold lowercase text-grafite">cardápio digital</p>
            <p class="mt-2 text-sm text-aco">
                Organize os produtos em categorias, defina disponibilidade por turno
                e publique o cardápio para os terminais de mesa.
            </p>
        </x-card>

        <x-card class="p-5 opacity-60">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-linha px-2 py-0.5">
                <span class="h-1.5 w-1.5 rounded-full bg-aco/50"></span>
                <span class="font-comanda text-[0.6rem] uppercase tracking-widest text-aco">em desenvolvimento</span>
            </div>
            <p class="text-sm font-semibold lowercase text-grafite">mesas e comandas</p>
            <p class="mt-2 text-sm text-aco">
                Abra e feche comandas por mesa, divida a conta e registre
                o consumo de cada cliente em tempo real.
            </p>
        </x-card>

        <x-card class="p-5 opacity-60">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-linha px-2 py-0.5">
                <span class="h-1.5 w-1.5 rounded-full bg-aco/50"></span>
                <span class="font-comanda text-[0.6rem] uppercase tracking-widest text-aco">em desenvolvimento</span>
            </div>
            <p class="text-sm font-semibold lowercase text-grafite">cozinha / kds</p>
            <p class="mt-2 text-sm text-aco">
                Display de cozinha com fila de pedidos, tempo de preparo e
                confirmação de entrega por item.
            </p>
        </x-card>
    </div>
</x-layouts.app>
