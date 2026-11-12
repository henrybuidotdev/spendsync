@props(['month'])

<div class="flex items-center justify-between">
    <button type="button" wire:click="previousMonth" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Previous month') }}"><x-icon name="chevron-left" /></button>
    <p class="font-semibold" aria-live="polite">{{ __('Month :month', ['month' => $month->format('m/Y')]) }}</p>
    <button type="button" wire:click="nextMonth" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Next month') }}"><x-icon name="chevron-right" /></button>
</div>
