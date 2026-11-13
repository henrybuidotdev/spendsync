{{-- Tabbed category grid; tapping a category calls the Livewire component's setCategory(id). Opens on the selected category's tab. --}}
@props(['categories', 'direction', 'selected' => null])

@php($types = $direction->categoryTypes())

<div x-data="{ tab: @js(($categories->firstWhere('id', $selected)?->type ?? $types[0])->value) }" {{ $attributes }}>
    <div class="tabs" role="group" aria-label="{{ __('Category type') }}">
        @foreach($types as $case)
            <button type="button" x-on:click="tab = @js($case->value)" x-bind:aria-pressed="tab === @js($case->value)" class="tab">{{ $case->label() }}</button>
        @endforeach
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
        @foreach($categories as $category)
            <button type="button" wire:click="setCategory({{ $category->id }})" wire:key="cat-{{ $category->id }}" x-show="tab === @js($category->type->value)"
                    aria-pressed="{{ $selected === $category->id ? 'true' : 'false' }}"
                    @class(['flex min-h-20 flex-col items-center justify-center gap-1 rounded-xl p-3 text-xs font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-white font-semibold ring-2 ring-black' => $selected === $category->id, 'bg-white ring-1 ring-slate-200 hover:bg-emerald-50' => $selected !== $category->id])>
                <x-category-icon :category="$category" class="h-8 w-8 text-xl" />
                <span class="mt-1 line-clamp-2 text-center">{{ $category->label }}</span>
            </button>
        @endforeach
    </div>
</div>
