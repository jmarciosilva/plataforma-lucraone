@php
    $selecionadas = old('branch_ids', $filiaisSelecionadas ?? []);
@endphp

<div class="mt-6">
    <p class="text-sm font-semibold lowercase text-grafite">
        filiais liberadas
        <span class="text-brasa" aria-hidden="true">*</span>
    </p>
    <p class="mt-1 text-xs text-aco">
        o operador só abre o caixa nas filiais marcadas. desmarcar encerra o caixa aberto dele naquela filial.
    </p>

    @error('branch_ids')
        <p class="mt-1 text-xs font-semibold text-brasa" role="alert">{{ $message }}</p>
    @enderror

    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($filiais as $filial)
            <label class="flex min-h-11 items-center gap-3 rounded-xl border border-linha bg-white px-3 text-sm text-grafite transition-colors hover:bg-nevoa">
                <input
                    type="checkbox"
                    name="branch_ids[]"
                    value="{{ $filial->id }}"
                    class="h-4 w-4 rounded border-linha text-sol focus:ring-sol/30"
                    @checked(in_array($filial->id, $selecionadas, true))
                >
                <span>
                    <span class="block font-semibold lowercase">{{ $filial->name }}</span>
                    <span class="block font-comanda text-[0.65rem] uppercase tracking-wider text-aco">{{ $filial->code }}</span>
                </span>
            </label>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <p class="text-sm text-aco">
                    nenhuma filial ativa neste estabelecimento.
                    @if (auth()->check() && auth()->user()->hasPermission('manage-branches', app(\App\Modules\Tenancy\Application\TenantContext::class)->id()))
                        <a href="{{ route('branches.create') }}" class="font-semibold text-sol transition-opacity hover:opacity-70">Cadastrar a primeira unidade.</a>
                    @else
                        peça ao administrador para cadastrar uma unidade antes de liberar operadores.
                    @endif
                </p>
            </div>
        @endforelse
    </div>
</div>
