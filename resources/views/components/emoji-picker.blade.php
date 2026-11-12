@props(['model', 'label'])

@php
    $choices = ['🍜', '🍚', '☕', '🧋', '🍺', '🛒', '🛵', '🚗', '⛽', '🚌', '✈️', '🏨',
        '🛍️', '👕', '💄', '💇', '🧾', '💡', '💧', '📱', '🌐', '🏠', '🛠️', '🎮',
        '🎬', '🎵', '💊', '🏥', '🏋️', '📚', '🎓', '👶', '🐶', '🎁', '💝', '🙏',
        '🔁', '💳', '💰', '📈', '➕', '📦'];
@endphp

<div x-data="{ open: false }" class="relative shrink-0" @keydown.escape.window="open = false">
    <button type="button" @click="open = ! open" :aria-expanded="open" aria-label="{{ $label }}"
            class="flex h-11 w-12 items-center justify-center rounded-xl border border-slate-300 bg-white text-xl hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
        <span x-text="$wire.{{ $model }} || '＋'" class="text-slate-500"></span>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition.opacity
         role="listbox" aria-label="{{ $label }}"
         class="absolute left-0 z-30 mt-2 w-[17.5rem] rounded-2xl bg-white p-2 shadow-lg ring-1 ring-slate-200">
        <div class="grid grid-cols-6 gap-1">
            @foreach($choices as $emoji)
                <button type="button" role="option" @click="$wire.{{ $model }} = '{{ $emoji }}'; open = false"
                        :aria-selected="$wire.{{ $model }} === '{{ $emoji }}'"
                        :class="$wire.{{ $model }} === '{{ $emoji }}' ? 'bg-emerald-100 ring-2 ring-emerald-500' : 'hover:bg-slate-100'"
                        class="flex h-11 w-11 items-center justify-center rounded-lg text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ $emoji }}</button>
            @endforeach
        </div>
        <button type="button" @click="$wire.{{ $model }} = ''; open = false"
                class="mt-1 min-h-11 w-full rounded-lg text-sm text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Không dùng biểu tượng') }}</button>
    </div>
</div>
