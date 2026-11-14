{{--
    Category picker: a card showing the selected category; tapping it opens the tabbed category tree in a modal, and picking one closes it.
    Tapping a category calls the Livewire component's setCategory(id). The tree opens on the selected category's tab.
    Top-level categories are rows; a parent's children are indented rows below it, joined by a tree line.
--}}
@props(['categories', 'direction', 'selected' => null])

@php
    $types = $direction->categoryTypes();
    $current = $categories->firstWhere('id', $selected);
    $currentParent = $current?->parent_id ? $categories->firstWhere('id', $current->parent_id) : null;
@endphp

<div x-data="{ tab: @js(($current?->type ?? $types[0])->value) }" {{ $attributes }}>
    <button type="button" x-on:click="$dispatch('open-modal', 'category-picker')" aria-haspopup="dialog"
            class="flex min-h-14 w-full items-center gap-3 rounded-xl bg-white px-2 text-left ring-1 ring-slate-200 hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
        <x-category-icon :category="$current" class="h-10 w-10 text-xl" />
        <span class="min-w-0 flex-1">
            @if($current === null)
                <span class="block font-medium text-slate-500">{{ __('Choose a category') }}</span>
            @else
                @if($currentParent)
                    <span class="block truncate text-xs text-slate-500">{{ $currentParent->label }}</span>
                @endif
                <span class="block truncate font-semibold">{{ $current->label }}</span>
            @endif
        </span>
        <x-icon name="chevron-down" class="h-5 w-5 shrink-0 text-slate-500" />
    </button>

    <x-modal name="category-picker" maxWidth="md" sheet>
        <div class="px-4 pb-4" role="dialog" aria-modal="true" aria-labelledby="category-picker-title">
            <div class="sticky top-0 z-10 -mx-4 flex items-center justify-between gap-2 bg-white px-4 py-3">
                <h2 id="category-picker-title" class="text-lg font-semibold">{{ __('Choose a category') }}</h2>
                <button type="button" x-on:click="$dispatch('close-modal', 'category-picker')" class="-mr-2 flex min-h-11 min-w-11 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Close') }}"><x-icon name="x" class="h-5 w-5" /></button>
            </div>

            <div class="tabs" role="group" aria-label="{{ __('Category type') }}">
                @foreach($types as $case)
                    <button type="button" x-on:click="tab = @js($case->value)" x-bind:aria-pressed="tab === @js($case->value)" class="tab">{{ $case->label() }}</button>
                @endforeach
            </div>

            @foreach($types as $case)
                <ul class="mt-3 space-y-2" x-show="tab === @js($case->value)">
                    @foreach($categories->where('type', $case)->whereNull('parent_id') as $parent)
                        @php($children = $categories->where('parent_id', $parent->id))

                        <li wire:key="cat-{{ $parent->id }}">
                            <x-category-picker-row :category="$parent" :selected="$selected" />

                            @if($children->isNotEmpty())
                                {{-- Tree lines start under the parent icon (center at 28px): a rounded elbow to each child's box, continuing down only while more children follow. --}}
                                <ul>
                                    @foreach($children as $child)
                                        <li wire:key="cat-{{ $child->id }}" @class([
                                            "relative pl-12 pt-2 before:absolute before:top-0 before:left-[28px] before:h-[calc(50%+0.25rem)] before:w-[20px] before:rounded-bl-lg before:border-b before:border-l before:border-slate-300 before:content-['']",
                                            "after:absolute after:bottom-0 after:left-[28px] after:top-1/2 after:border-l after:border-slate-300 after:content-['']" => ! $loop->last,
                                        ])>
                                            <x-category-picker-row :category="$child" :selected="$selected" child />
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </x-modal>
</div>
