@if (collect($onboardingChecklist)->contains('concluido', false))
    <x-section-label class="mt-8">Primeiros passos</x-section-label>
    <x-card>
        <p class="text-sm text-aco">Siga os passos abaixo na ordem indicada para preparar seu estabelecimento para começar a operar. O progresso acompanha os cadastros reais do negócio.</p>
        <ol class="mt-4 list-none space-y-3" aria-label="Etapas de configuração do estabelecimento">
            @foreach ($onboardingChecklist as $item)
                <li class="flex items-center gap-2 text-sm text-grafite">
                    <span aria-label="{{ $item['concluido'] ? 'Concluído' : 'Pendente' }}">{{ $item['concluido'] ? '✅' : '⬜' }}</span>
                    @if (! $item['concluido'] && $item['rota'] && auth()->user()->hasPermission($item['permissao']))
                        <a href="{{ route($item['rota']) }}" class="font-semibold text-sol hover:underline">{{ $loop->iteration }}. {{ $item['rotulo'] }}</a>
                    @else
                        <span>{{ $loop->iteration }}. {{ $item['rotulo'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
        <p class="mt-4 text-xs text-aco">Equipe: além do administrador inicial, cadastre outra pessoa com conta e acesso ativos. Acesso de suporte da plataforma não conta como equipe do cliente.</p>
    </x-card>
@endif
