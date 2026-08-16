@php
    // O estabelecimento em uso vem do contexto, não do usuário: desde o F1.8
    // uma pessoa pode estar associada a vários.
    $contexto = app(\App\Modules\Tenancy\Application\TenantContext::class);
    $tenantNome = $contexto->resolved() ? $contexto->tenant()->name : null;
@endphp

<x-layouts.app title="dashboard" :tenantNome="$tenantNome">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full bg-ok"></span>
        visão geral do estabelecimento
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" href="{{ route('dashboard') }}">atualizar</x-button>
    </x-slot:acoes>

    <x-section-label>o painel até agora</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="tenants" valor="—" nota="disponível no F3.4" />
        <x-kpi rotulo="usuários" valor="—" nota="disponível no F3.5" />
        <x-kpi rotulo="empresas" valor="—" nota="disponível no F3.6" />
        <x-kpi rotulo="produtos" valor="—" nota="API pronta no F2.1" />
    </div>

    <x-section-label class="mt-8">próximos passos</x-section-label>

    <x-card>
        <p class="text-sm text-grafite">
            O esqueleto do painel está no ar. Os widgets acima passam a mostrar
            números reais conforme cada sprint entrega seu módulo.
        </p>

        <ul class="mt-4 space-y-2 text-sm text-aco">
            <li class="flex items-center gap-2">
                <x-badge tipo="destaque">F3.2</x-badge>
                tela de login com sessão e bloqueio de usuário inativo
            </li>
            <li class="flex items-center gap-2">
                <x-badge>F3.3</x-badge>
                widgets com contagens reais do banco
            </li>
            <li class="flex items-center gap-2">
                <x-badge>F3.4</x-badge>
                cadastro de estabelecimentos pela interface
            </li>
        </ul>
    </x-card>
</x-layouts.app>
