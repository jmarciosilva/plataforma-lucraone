<x-layouts.app title="tenants" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $tenant->trashed() ? 'bg-brasa' : ($tenant->isActive() ? 'bg-ok' : 'bg-alerta') }}"></span>
        {{ $tenant->trashed() ? 'estabelecimento arquivado' : 'detalhe do estabelecimento' }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-tenants')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('tenants.index') }}">voltar</x-button>
        @if (! $tenant->trashed())
            <x-button href="{{ route('tenants.edit', $tenant) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    @include('tenants._help')

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="usuários" :valor="$tenant->active_users_count" nota="vínculos ativos" />
        <x-kpi rotulo="empresas" :valor="$tenant->companies_count" nota="empresas vinculadas" />
        <x-kpi rotulo="status" :valor="strtolower($tenant->status)" nota="estado operacional" />
        <x-kpi rotulo="plano" :valor="$tenant->plan" nota="assinatura atual" />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $tenant->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">slug</dt>
                    <dd class="mt-1 font-comanda text-xs uppercase tracking-wider text-grafite">{{ $tenant->slug }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">timezone</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $tenant->timezone }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">locale e moeda</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $tenant->locale }} · {{ $tenant->currency }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">criado em</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $tenant->created_at->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">atualizado em</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $tenant->updated_at->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($tenant->trashed())
                <form method="POST" action="{{ route('tenants.restore', $tenant->id) }}" class="mt-4">
                    @csrf
                    <x-button tipo="submit" class="w-full">restaurar tenant</x-button>
                </form>
            @else
                <x-button variante="secundario" href="{{ route('tenants.edit', $tenant) }}" class="mt-4 w-full">editar dados</x-button>

                <button
                    type="button"
                    x-data
                    @click="$dispatch('abrir-modal', 'arquivar-tenant')"
                    class="mt-2 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-brasa px-4 text-sm font-semibold lowercase text-white transition-opacity hover:opacity-90"
                >
                    arquivar tenant
                </button>

                <x-modal nome="arquivar-tenant" titulo="arquivar tenant">
                    <p class="text-sm text-aco">
                        O tenant será removido das listagens padrão e poderá ser restaurado depois.
                    </p>

                    <x-slot:acoes>
                        <form method="POST" action="{{ route('tenants.destroy', $tenant) }}">
                            @csrf
                            @method('DELETE')
                            <x-button variante="perigo" tipo="submit">confirmar arquivo</x-button>
                        </form>
                    </x-slot:acoes>
                </x-modal>
            @endif
        </x-card>
    </div>
</x-layouts.app>
