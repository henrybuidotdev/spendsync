@props(['month'])

<div class="flex items-center justify-between rounded-2xl bg-white p-1 shadow-sm">
    <button type="button" wire:click="previousMonth" class="flex h-11 w-11 items-center justify-center rounded-xl text-2xl text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Tháng trước') }}">‹</button>
    <p class="font-semibold" aria-live="polite">{{ __('Tháng :month', ['month' => $month->format('m/Y')]) }}</p>
    <button type="button" wire:click="nextMonth" class="flex h-11 w-11 items-center justify-center rounded-xl text-2xl text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Tháng sau') }}">›</button>
</div>
