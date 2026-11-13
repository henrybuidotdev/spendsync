@props(['dismissAfter' => 4000, 'key' => 'status'])

@if(session($key))
    @php($link = session("{$key}_link"))

    <div role="status" wire:key="flash-{{ $key }}-{{ uniqid() }}"
         x-data="{ shown: true }" x-init="setTimeout(() => shown = false, {{ $link ? $dismissAfter * 2 : $dismissAfter }})" x-show="shown" x-transition.opacity.duration.300ms
         {{ $attributes->merge(['class' => 'fixed inset-x-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-lg ring-1 ring-emerald-200 sm:left-auto sm:right-6 sm:w-96']) }}>
        {{ session($key) }}

        @if($link)
            <a href="{{ $link['url'] }}" wire:navigate class="font-semibold underline">{{ $link['label'] }}</a>
        @endif
    </div>
@endif
