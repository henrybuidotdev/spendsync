@php use App\Support\Format; @endphp

<div class="space-y-4">

    <a href="{{ route('transactions', ['month' => Format::local($transaction->occurred_at)->format('Y-m'), 'filter' => $backFilter]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">‹ {{ __('Transactions') }}</a>

    <div class="rounded-2xl bg-white p-5 text-center shadow-sm">
        <x-category-icon :category="$transaction->category" class="mx-auto h-16 w-16 text-4xl" />
        <p class="mt-2 break-words font-semibold">{{ $transaction->label() }}</p>
        <x-amount :transaction="$transaction" class="mt-1 block text-3xl" />
        <p class="mt-1 text-sm text-slate-500">{{ ucfirst(Format::local($transaction->occurred_at)->translatedFormat('l, d/m/Y · H:i')) }}</p>

        <div class="relative mt-4 text-left">
            <label for="note" class="sr-only">{{ __('Note') }}</label>
            <input type="text" id="note" wire:model.live.debounce.1000ms="note" maxlength="1000" autocomplete="off"
                   placeholder="{{ __('+ Add a note') }}"
                   class="block min-h-11 w-full rounded-xl border-transparent bg-transparent px-20 text-center text-base text-slate-600 placeholder:text-slate-400 hover:bg-slate-50 focus:border-slate-200 focus:bg-white focus:ring-0">
            <x-action-message on="note-saved" class="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-1 text-sm font-medium text-emerald-700">
                <span aria-hidden="true">✓</span> {{ __('Saved') }}
            </x-action-message>
        </div>
        @error('note') <p class="mt-1 text-left text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <dl class="divide-y divide-slate-100 rounded-2xl bg-white px-4 text-sm shadow-sm">
        @foreach([
            __('Bank') => "{$bankName} ({$transaction->bank_code})",
            __('Type') => $transaction->isIncome() ? __('Money in') : __('Money out'),
            __('Original amount') => $transaction->currency ? Format::foreign($transaction->original_amount, $transaction->currency).($transaction->amount_estimated ? ' · '.__('estimated conversion') : '') : null,
            __('Counterparty') => $transaction->counterparty,
            __('Account number') => $transaction->counterparty_account,
            __('Description') => $transaction->description,
            // __('Balance after') => $transaction->balance_after !== null ? Format::money($transaction->balance_after) : null,
            __('Reference') => $transaction->reference,
        ] as $label => $value)
            <div class="flex justify-between gap-4 py-3">
                <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ $value ?? '—' }}</dd>
            </div>
        @endforeach
    </dl>

    <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="category-heading"
             x-data="{ tab: @js($defaultTab) }">
        <h2 id="category-heading" class="font-semibold">{{ __('Category') }}</h2>

        <div class="mt-4 grid grid-cols-3 gap-1 rounded-full bg-slate-100 p-1" role="group" aria-label="{{ __('Category type') }}">
            @foreach(\App\Enums\CategoryType::cases() as $case)
                <button type="button" x-on:click="tab = @js($case->value)" x-bind:aria-pressed="tab === @js($case->value)"
                        class="min-h-11 rounded-full text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                        x-bind:class="tab === @js($case->value) ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">{{ $case->label() }}</button>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
            @foreach($categories as $category)
                <button type="button" wire:click="setCategory({{ $category->id }})" wire:key="cat-{{ $category->id }}" x-show="tab === @js($category->type->value)"
                        aria-pressed="{{ $transaction->category_id === $category->id ? 'true' : 'false' }}"
                        @class(['flex min-h-20 flex-col items-center justify-center gap-1 rounded-xl p-3 text-xs font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-black text-white' => $transaction->category_id === $category->id, 'bg-slate-50 ring-1 ring-slate-200 hover:bg-emerald-50' => $transaction->category_id !== $category->id])>
                    <x-category-icon :category="$category" class="h-8 w-8 text-xl" />
                    <span class="mt-1 line-clamp-2 text-center">{{ $category->name }}</span>
                </button>
            @endforeach
        </div>


        @if($this->ruleTarget())
            <label class="mt-5 flex min-h-11 cursor-pointer items-start gap-3 text-sm">
                <input type="checkbox" wire:model="applyToFuture" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>{{ __('Apply to future transactions with') }} <em class="font-semibold not-italic">{{ \Illuminate\Support\Str::limit($this->ruleTarget(), 60) }}</em></span>
            </label>
        @endif

        <x-flash class="mt-4" :dismiss-after="4000" />
    </section>

    @if($rawEmail)
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <button type="button" wire:click="toggleRaw" aria-expanded="{{ $showRaw ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center justify-between font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                {{ __('View original email') }} <span aria-hidden="true">{{ $showRaw ? '▴' : '▾' }}</span>
            </button>
            @if($showRaw)
                <p class="mt-2 text-xs text-slate-500">{{ $rawEmail->subject }} · {{ Format::local($rawEmail->received_at)->format('d/m/Y H:i') }}</p>
                <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-3 font-mono text-xs">{{ $rawText }}</pre>
            @endif
        </section>
    @endif
</div>
