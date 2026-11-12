@props(['dismissAfter' => null])

@if(session('status'))
    <div role="status" {{ $attributes->merge(['class' => 'rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800']) }}
         @if($dismissAfter)
             wire:key="flash-{{ uniqid() }}" x-data="{ shown: true }" x-init="setTimeout(() => shown = false, {{ $dismissAfter }})" x-show="shown" x-transition.opacity.duration.300ms
         @endif>
        {{ session('status') }}
    </div>
@endif
