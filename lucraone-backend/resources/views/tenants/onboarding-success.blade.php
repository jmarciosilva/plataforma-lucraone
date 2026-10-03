<x-layouts.app title="Cliente criado com sucesso" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo><span class="h-2 w-2 rounded-full bg-ok"></span>Próximos passos</x-slot:subtitulo>
    <x-card>
        <h2 class="text-lg font-semibold text-grafite">{{ $tenant->name }}</h2>
        <p class="mt-2 text-sm text-aco">Administrador: {{ $administrador->name }} · {{ $administrador->email }}</p>
        <p class="mt-2 text-sm text-aco">{{ $identidadeNova ? 'Nova conta criada para o administrador.' : 'Conta existente vinculada; seus dados foram mantidos.' }}</p>
        <p class="mt-2 text-sm text-aco">{{ $empresaConfigurada ? 'Empresa configurada' : 'Empresa pendente — configure antes do primeiro produto.' }}</p>
        <p class="mt-4 text-sm text-grafite">A configuração inicial começa pela empresa, seguida de categoria, produto, preço, estoque e equipe.</p>
        @if ($podeEntrar)
            <p class="mt-3 text-sm text-aco">Você manteve seu acesso local. Cada atalho abaixo seleciona este estabelecimento antes de abrir a configuração.</p>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['dashboard' => 'Entrar no estabelecimento', 'companies.create' => 'Configurar empresa', 'catalog.categories.create' => 'Cadastrar primeira categoria', 'catalog.products.create' => 'Cadastrar primeiro produto', 'users.create' => 'Cadastrar equipe'] as $destino => $rotulo)
                    <form method="POST" action="{{ route('estabelecimentos.definir') }}">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <input type="hidden" name="destino" value="{{ $destino }}">
                        <x-button tipo="submit" variante="secundario" class="w-full">{{ $rotulo }}</x-button>
                    </form>
                @endforeach
            </div>
        @else
            <p class="mt-3 rounded-xl bg-nevoa p-4 text-sm text-grafite">Você não tem acesso operacional a este estabelecimento ou ele está suspenso/encerrado. Oriente o administrador do cliente a entrar com sua própria conta e seguir o checklist no dashboard. Sua autoridade de plataforma foi mantida.</p>
        @endif
        <x-button href="{{ route('tenants.index') }}" class="mt-5">Voltar para Clientes do LucraOne</x-button>
    </x-card>
</x-layouts.app>
