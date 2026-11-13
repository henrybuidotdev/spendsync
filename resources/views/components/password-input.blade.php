@props(['id' => 'password'])

<div x-data="{ shown: false }" {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <x-text-input {{ $attributes->except('class') }} :id="$id" class="block w-full pe-12" x-bind:type="shown ? 'text' : 'password'" type="password" />
    <button type="button" x-on:click="shown = ! shown" x-bind:aria-pressed="shown" aria-controls="{{ $id }}" aria-label="{{ __('Show password') }}"
            class="absolute inset-y-0 end-0 flex w-11 items-center justify-center rounded-e-xl text-slate-500 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950">
        <x-icon name="eye" x-show="! shown" />
        <x-icon name="eye-off" x-show="shown" x-cloak />
    </button>
</div>
