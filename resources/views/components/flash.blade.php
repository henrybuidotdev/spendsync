@props(['dismissAfter' => null, 'key' => 'status'])

@if(session($key))
    @php($link = session("{$key}_link"))

    <div role="status" {{ $attributes->merge(['class' => 'rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800']) }}
         @if($dismissAfter && ! $link)
             wire:key="flash-{{ uniqid() }}" x-data="{ shown: true }" x-init="setTimeout(() => shown = false, {{ $dismissAfter }})" x-show="shown" x-transition.opacity.duration.300ms
         @endif>
        {{ session($key) }}

        @if($link)
            <a href="{{ $link['url'] }}" wire:navigate class="font-semibold underline">{{ $link['label'] }}</a>
        @endif
    </div>
@endif
