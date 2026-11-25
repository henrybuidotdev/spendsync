{{-- One row of the category picker; a child row is a bit smaller than a top-level one. --}}
@props(['category', 'selected' => null, 'child' => false])

<button type="button" wire:click="setCategory({{ $category->id }})" x-on:click="$dispatch('close-modal', 'category-picker')"
        aria-pressed="{{ $selected === $category->id ? 'true' : 'false' }}"
        @class(['flex w-full items-center gap-3 rounded-xl px-2 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'min-h-12 text-sm' => $child, 'min-h-14 text-base font-medium' => ! $child, 'bg-emerald-100 font-semibold' => $selected === $category->id, 'bg-white ring-1 ring-slate-200 hover:bg-emerald-50' => $selected !== $category->id])>
    <x-category-icon :category="$category" @class(['h-8 w-8 text-lg' => $child, 'h-10 w-10 text-xl' => ! $child]) />
    <span class="min-w-0 flex-1 truncate">{{ $category->label }}</span>
    @if($selected === $category->id)
        <x-icon name="check" class="mr-1 h-5 w-5 shrink-0 text-emerald-700" />
    @endif
</button>
