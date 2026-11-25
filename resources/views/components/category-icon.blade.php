@props(['category' => null])

@if($category === null)
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-amber-100 font-bold text-amber-700') }} aria-hidden="true">?</span>
@else
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full font-bold text-slate-700') }} style="background-color: rgb(var(--c-chart-{{ $category->color }}) / 0.32)" aria-hidden="true">{{ filled($category->icon) ? $category->icon : mb_strtoupper(mb_substr($category->label, 0, 1)) }}</span>
@endif
