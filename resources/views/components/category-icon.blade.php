@props(['category' => null])

@if($category === null)
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-amber-100 font-bold text-amber-700') }} aria-hidden="true">?</span>
@elseif(filled($category->icon))
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-slate-100') }} aria-hidden="true">{{ $category->icon }}</span>
@else
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-700') }} aria-hidden="true">{{ mb_strtoupper(mb_substr($category->name, 0, 1)) }}</span>
@endif
