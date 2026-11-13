<div class="space-y-4">
    <a href="{{ $backUrl }}" wire:navigate class="-ml-1 inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"><x-icon name="chevron-left" class="h-4 w-4" /> {{ __('Back') }}</a>

    <h1 class="text-2xl font-semibold tracking-tight">{{ $transaction ? __('Edit transaction') : __('Add transaction') }}</h1>

    <form wire:submit="save" class="space-y-4" novalidate>
        <div class="tabs" role="group" aria-label="{{ __('Type') }}">
            @foreach([\App\Enums\Direction::Out, \App\Enums\Direction::In] as $case)
                <button type="button" wire:click="setDirection('{{ $case->value }}')" aria-pressed="{{ $direction === $case ? 'true' : 'false' }}" class="tab">{{ $case->label() }}</button>
            @endforeach
        </div>

        <div>
            <label for="amount" class="block text-sm font-medium">{{ __('Amount (₫)') }}</label>
            {{-- Digits only, no leading zero, grouped as 1.000.000 while typing; the caret stays after the same digit. --}}
            <input id="amount" type="text" inputmode="numeric" autocomplete="off" placeholder="50.000" required
                   x-data="{
                       amount: $wire.entangle('amount'),
                       format(el) {
                           const digitsBeforeCaret = el.value.slice(0, el.selectionStart).replace(/\D/g, '').replace(/^0+/, '').length;
                           const digits = el.value.replace(/\D/g, '').replace(/^0+/, '').slice(0, 15);
                           el.value = this.amount = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                           let caret = 0;
                           for (let seen = 0; caret < el.value.length && seen < digitsBeforeCaret; caret++) {
                               if (el.value[caret] !== '.') seen++;
                           }
                           el.setSelectionRange(caret, caret);
                       },
                   }"
                   x-bind:value="amount" x-on:input="format($el)"
                   class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 text-base font-num focus:border-emerald-500 focus:ring-emerald-500">
            @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="counterparty" class="block text-sm font-medium">{{ __('Counterparty') }}</label>
            <input wire:model="counterparty" id="counterparty" type="text" maxlength="255" autocomplete="off" placeholder="{{ __('e.g. Coffee, Grab, Rent') }}" required
                   class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
            @error('counterparty') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="occurredAt" class="block text-sm font-medium">{{ __('Date and time') }}</label>
            <input wire:model="occurredAt" id="occurredAt" type="datetime-local" required
                   class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
            @error('occurredAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <section aria-labelledby="category-heading">
            <h2 id="category-heading" class="text-sm font-medium">{{ __('Category') }}</h2>

            <x-category-picker :categories="$categories" :direction="$direction" :selected="$categoryId" wire:key="categories-{{ $direction->value }}" class="mt-2" />
            @error('categoryId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <div>
            <label for="note" class="block text-sm font-medium">{{ __('Note') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></label>
            <input wire:model="note" id="note" type="text" maxlength="1000" autocomplete="off"
                   class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
            @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-black px-4 font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:opacity-60">
            {{ $transaction ? __('Save') : __('Add transaction') }}
        </button>
    </form>
</div>
