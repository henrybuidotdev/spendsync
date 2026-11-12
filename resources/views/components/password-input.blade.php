@props(['id' => 'password'])

<div x-data="{ shown: false }" {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <x-text-input {{ $attributes->except('class') }} :id="$id" class="block w-full pe-12" x-bind:type="shown ? 'text' : 'password'" type="password" />
    <button type="button" x-on:click="shown = ! shown" x-bind:aria-pressed="shown" aria-controls="{{ $id }}" aria-label="{{ __('Hiện mật khẩu') }}"
            class="absolute inset-y-0 end-0 flex w-11 items-center justify-center rounded-e-xl text-slate-500 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950">
        <svg x-show="! shown" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg x-show="shown" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.7 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-2.2 3.2M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2M2 2l20 20"/></svg>
    </button>
</div>
