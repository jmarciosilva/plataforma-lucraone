@if (collect($onboardingChecklist)->contains('concluido', false))
    <x-section-label class="mt-8">Primeiros passos</x-section-label>
    <x-card>
        <p class="text-sm text-aco">Prepare este estabelecimento para começar. Configure a empresa antes do primeiro produto. O progresso acompanha os cadastros reais do negócio.</p>
        <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($onboardingChecklist as $item)
                <li class="flex items-center gap-2 text-sm text-grafite">
                    <span aria-label="{{ $item['concluido'] ? 'Concluído' : 'Pendente' }}">{{ $item['concluido'] ? '✅' : '⬜' }}</span>
                    @if (! $item['concluido'] && $item['rota'] && auth()->user()->hasPermission($item['permissao']))
                        <a href="{{ route($item['rota']) }}" class="font-semibold text-sol hover:underline">{{ $item['rotulo'] }}</a>
                    @else
                        <span>{{ $item['rotulo'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="mt-4 text-xs text-aco">Equipe: além do administrador inicial, cadastre outra pessoa com conta e acesso ativos. Acesso de suporte da plataforma não conta como equipe do cliente.</p>
    </x-card>
@endif
