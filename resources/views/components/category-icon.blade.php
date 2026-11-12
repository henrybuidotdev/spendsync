@props(['icon' => null])

@if($icon)
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-slate-100') }} aria-hidden="true">{{ $icon }}</span>
@else
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-amber-100 font-bold text-amber-700') }} aria-hidden="true">?</span>
@endif
