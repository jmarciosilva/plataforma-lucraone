@php
    $parentOptions = $parents->mapWithKeys(fn ($parent) => [
        $parent->id => $parent->name,
    ])->all();
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-form-group nome="name" rotulo="nome" obrigatorio>
        <x-input nome="name" :valor="$category->name" />
    </x-form-group>

    <x-form-group nome="slug" rotulo="slug">
        <x-input nome="slug" :valor="$category->slug" placeholder="gerado pelo nome" />
    </x-form-group>

    <x-form-group nome="parent_id" rotulo="categoria pai">
        <x-select nome="parent_id" :opcoes="$parentOptions" :valor="$category->parent_id" vazio="sem categoria pai" />
    </x-form-group>

    <x-form-group nome="description" rotulo="descrição" class="lg:col-span-2">
        <x-input tipo="textarea" nome="description" :valor="$category->description" rows="4" />
    </x-form-group>
</div>
