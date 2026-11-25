@props(['month'])

<div class="flex items-center justify-between">
    <button type="button" wire:click="previousMonth" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Previous month') }}"><x-icon name="chevron-left" /></button>
    <p class="font-semibold" aria-live="polite">{{ __(':monthName :year', ['monthName' => $month->translatedFormat('F'), 'number' => $month->format('m'), 'year' => $month->year]) }}</p>
    <button type="button" wire:click="nextMonth" @disabled($month->gte(\App\Support\Format::month(null))) class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:opacity-30 disabled:hover:bg-transparent" aria-label="{{ __('Next month') }}"><x-icon name="chevron-right" /></button>
</div>
