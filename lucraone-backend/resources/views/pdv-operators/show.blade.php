<x-layouts.app title="operadores de pdv" :tenantNome="$tenantNome" :breadcrumbs="$breadcrumbs">
    <x-slot:subtitulo>
        <span class="h-2 w-2 rounded-full {{ $perfil->isActive() ? 'bg-ok' : 'bg-alerta' }}"></span>
        {{ $perfil->isActive() ? 'operador ativo' : 'operador inativo' }}
    </x-slot:subtitulo>

    <x-slot:acoes>
        <x-button variante="secundario" x-data @click="$dispatch('abrir-modal', 'help-pdv-operators')">ajuda</x-button>
        <x-button variante="secundario" href="{{ route('pdv-operators.index') }}">voltar</x-button>
        @if ($podeAdministrar)
            <x-button href="{{ route('pdv-operators.edit', $perfil) }}">editar</x-button>
        @endif
    </x-slot:acoes>

    @include('pdv-operators._help')

    @if (session('senha_temporaria'))
        <x-alert tipo="atencao" class="mb-6">
            senha temporária: <strong class="font-comanda">{{ session('senha_temporaria') }}</strong> —
            anote agora e entregue ao operador. ela não será mostrada de novo.
        </x-alert>
    @endif

    <x-section-label>resumo</x-section-label>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi rotulo="usuário do pdv" :valor="$perfil->username" nota="digitado no caixa" />
        <x-kpi rotulo="situação" :valor="$perfil->isActive() ? 'ativo' : 'inativo'" nota="acesso ao caixa" />
        <x-kpi
            rotulo="filiais liberadas"
            :valor="$atribuicoes->where('status', 'ACTIVE')->count()"
            nota="onde pode operar"
        />
        <x-kpi
            rotulo="caixa agora"
            :valor="$sessao ? 'aberto' : 'fechado'"
            :nota="$sessao ? ($sessao->terminal?->name ?? 'terminal') : 'nenhuma sessão'"
        />
    </div>

    <x-section-label class="mt-8">dados</x-section-label>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs lowercase text-aco">nome</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $perfil->user?->name ?? 'pessoa removida' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">e-mail</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $perfil->user?->email ?? 'sem e-mail' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">usuário do pdv</dt>
                    <dd class="mt-1 font-comanda text-sm text-grafite">{{ $perfil->username }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">acesso ao estabelecimento</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ strtolower($membership?->status ?? 'sem vínculo') }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">cadastrado em</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $perfil->created_at?->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">cadastrado por</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $perfil->createdBy?->name ?? 'não registrado' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold lowercase text-grafite">ações</p>

            @if ($podeAdministrar)
                <form
                    method="POST"
                    action="{{ route('pdv-operators.toggle', $perfil) }}"
                    class="mt-3"
                    onsubmit="return confirm('{{ $perfil->isActive() ? 'desativar este operador encerra o caixa aberto dele. continuar?' : 'ativar este operador?' }}')"
                >
                    @csrf
                    <x-button tipo="submit" :variante="$perfil->isActive() ? 'perigo' : 'primario'" class="w-full">
                        {{ $perfil->isActive() ? 'desativar operador' : 'ativar operador' }}
                    </x-button>
                </form>

                @if ($podeRedefinirSenha)
                    <form
                        method="POST"
                        action="{{ route('pdv-operators.reset-password', $perfil) }}"
                        class="mt-2"
                        onsubmit="return confirm('gerar uma senha temporária para este operador?')"
                    >
                        @csrf
                        <x-button tipo="submit" variante="secundario" class="w-full">redefinir senha</x-button>
                    </form>
                @else
                    <p class="mt-3 text-xs text-aco">
                        a senha desta pessoa não pode ser redefinida aqui: ela também tem acesso a outro
                        estabelecimento, e a senha é da conta dela, não deste caixa.
                    </p>
                @endif
            @else
                <p class="mt-3 text-xs text-aco">você tem permissão apenas para consultar.</p>
            @endif
        </x-card>
    </div>

    <x-section-label class="mt-8">filiais</x-section-label>

    <x-table :cabecalhos="['filial', 'situação', 'liberada em', 'revogada em']">
        @forelse ($atribuicoes as $atribuicao)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4">
                    <span class="font-semibold lowercase text-grafite">{{ $atribuicao->branch?->name ?? 'filial removida' }}</span>
                    <p class="font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $atribuicao->branch?->code }}</p>
                </td>
                <td class="px-5 py-4">
                    <x-badge :tipo="$atribuicao->isActive() ? 'sucesso' : 'erro'">
                        {{ $atribuicao->isActive() ? 'liberada' : 'revogada' }}
                    </x-badge>
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $atribuicao->created_at?->format('d/m/Y H:i') }}</td>
                <td class="px-5 py-4 text-sm text-aco">{{ $atribuicao->revoked_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-5 py-10 text-center text-sm text-aco">
                    nenhuma filial liberada. sem filial, o operador não abre caixa nenhum.
                </td>
            </tr>
        @endforelse
    </x-table>

    @if ($sessao)
        <x-section-label class="mt-8">caixa aberto</x-section-label>

        <x-card>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs lowercase text-aco">terminal</dt>
                    <dd class="mt-1 text-sm font-semibold text-grafite">{{ $sessao->terminal?->name ?? 'terminal' }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">aberto desde</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $sessao->authenticated_at?->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">última atividade online</dt>
                    <dd class="mt-1 text-sm text-grafite">{{ $sessao->last_revalidated_at?->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs lowercase text-aco">fim da jornada</dt>
                    <dd class="mt-1 text-sm text-grafite">
                        {{ $sessao->absolute_expires_at?->format('d/m/Y H:i') ?? 'sem limite de horas' }}
                    </dd>
                </div>
            </dl>

            <p class="mt-4 text-xs text-aco">
                identificador da sessão <span class="font-comanda">{{ $sessao->id }}</span> ·
                {{ $sessao->machine_rotations }} renovação(ões) técnica(s) do terminal durante este turno.
                este identificador serve para suporte e não autentica nada.
            </p>
        </x-card>
    @endif

    <x-section-label class="mt-8">histórico</x-section-label>

    <x-table :cabecalhos="['quando', 'ação', 'quem fez', 'detalhe']">
        @forelse ($historico as $registro)
            <tr class="border-b border-linha last:border-0">
                <td class="px-5 py-4 text-sm text-aco">{{ $registro->created_at?->format('d/m/Y H:i') }}</td>
                <td class="px-5 py-4 text-sm font-semibold lowercase text-grafite">
                    {{ str_replace(['pdv.operator.', '_'], ['', ' '], $registro->action) }}
                </td>
                <td class="px-5 py-4 text-sm text-aco">{{ $registro->user?->name ?? 'sistema' }}</td>
                <td class="px-5 py-4 text-xs text-aco">
                    @php
                        // Só rótulos operacionais. Nada de senha, credencial ou
                        // hash chega aqui — a auditoria não grava esses campos.
                        $detalhes = collect($registro->changes ?? [])
                            ->only(['username', 'previous_username', 'status', 'previous_status', 'branch_id', 'reason', 'machine_rotations'])
                            ->filter(fn ($valor) => $valor !== null && $valor !== '');
                    @endphp

                    {{ $detalhes->isEmpty() ? '—' : $detalhes->map(fn ($valor, $chave) => "{$chave}: {$valor}")->implode(' · ') }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-5 py-10 text-center text-sm text-aco">
                    nenhuma alteração registrada ainda.
                </td>
            </tr>
        @endforelse
    </x-table>
</x-layouts.app>
