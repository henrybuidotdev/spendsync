@php use App\Support\Format; @endphp

<div class="space-y-4">
    <x-flash key="visibility" />

    <a href="{{ route('transactions', ['month' => Format::local($transaction->occurred_at)->format('Y-m'), 'filter' => $backFilter]) }}" wire:navigate class="-ml-1 inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"><x-icon name="chevron-left" class="h-4 w-4" /> {{ __('Transactions') }}</a>

    @if($transaction->trashed())
        <div class="flex items-center justify-between gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200" role="status">
            <p>{{ __('This transaction is hidden. It is not counted in totals or reports.') }}</p>
            <button type="button" wire:click="toggleHidden" class="min-h-11 shrink-0 rounded-xl bg-black px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Unhide') }}</button>
        </div>
    @endif

    <div class="py-2 text-center">
        <x-category-icon :category="$transaction->category" class="mx-auto h-16 w-16 text-4xl" />
        <p class="mt-2 break-words font-semibold">{{ $transaction->label() }}</p>
        <x-amount :transaction="$transaction" class="mt-1 block text-3xl" />
        <p class="mt-1 text-sm text-slate-500">{{ ucfirst(Format::local($transaction->occurred_at)->translatedFormat('l, d/m/Y · H:i')) }}</p>

        <div class="relative mt-4 text-left">
            <label for="note" class="sr-only">{{ __('Note') }}</label>
            <input type="text" id="note" wire:model.live.debounce.1000ms="note" maxlength="1000" autocomplete="off"
                   placeholder="{{ __('+ Add a note') }}"
                   class="block min-h-11 w-full rounded-xl border-transparent !bg-transparent px-20 text-center text-base text-slate-600 placeholder:text-slate-400 hover:!bg-slate-100 focus:border-slate-200 focus:!bg-white focus:ring-0">
            <x-action-message on="note-saved" class="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-1 text-sm font-medium text-emerald-700">
                <x-icon name="check" class="h-4 w-4" /> {{ __('Saved') }}
            </x-action-message>
        </div>
        @error('note') <p class="mt-1 text-left text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <section class="border-t border-slate-200 pt-4" aria-labelledby="category-heading"
             x-data="{ tab: @js($defaultTab) }">
        <h2 id="category-heading" class="font-semibold">{{ __('Category') }}</h2>

        <div class="tabs mt-3" role="group" aria-label="{{ __('Category type') }}">
            @foreach(\App\Enums\CategoryType::cases() as $case)
                <button type="button" x-on:click="tab = @js($case->value)" x-bind:aria-pressed="tab === @js($case->value)" class="tab">{{ $case->label() }}</button>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
            @foreach($categories as $category)
                <button type="button" wire:click="setCategory({{ $category->id }})" wire:key="cat-{{ $category->id }}" x-show="tab === @js($category->type->value)"
                        aria-pressed="{{ $transaction->category_id === $category->id ? 'true' : 'false' }}"
                        @class(['flex min-h-20 flex-col items-center justify-center gap-1 rounded-xl p-3 text-xs font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-white font-semibold ring-2 ring-black' => $transaction->category_id === $category->id, 'bg-white ring-1 ring-slate-200 hover:bg-emerald-50' => $transaction->category_id !== $category->id])>
                    <x-category-icon :category="$category" class="h-8 w-8 text-xl" />
                    <span class="mt-1 line-clamp-2 text-center">{{ $category->label }}</span>
                </button>
            @endforeach
        </div>


        @if($transaction->category_id && $this->ruleTarget())
            @php($ruleTexts = ['target' => '<em class="font-semibold not-italic">'.e(\Illuminate\Support\Str::limit($this->ruleTarget(), 60)).'</em>', 'category' => e($transaction->category->label)])

            @if($backfillCount > 0)
                <div class="mt-5 flex items-center gap-3 rounded-xl bg-amber-50 p-3 text-sm">
                    <p class="flex-1">{!! __('Found :count "Uncategorized" transactions with :target. Set them to ":category" too?', ['count' => $backfillCount, ...$ruleTexts]) !!}</p>
                    <x-primary-button type="button" wire:click="applyToUncategorized" class="shrink-0">{{ __('Apply') }}</x-primary-button>
                </div>
            @endif

            <label class="mt-5 flex min-h-11 cursor-pointer items-start gap-3 text-sm">
                <input type="checkbox" wire:model.live="applyToFuture" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>{!! __('Automatically set ":category" for future transactions with :target', $ruleTexts) !!}</span>
            </label>
        @endif

        <x-flash />
    </section>

    <dl class="divide-y divide-slate-200 border-y border-slate-200 text-sm">
        @foreach([
            __('Bank') => "{$bankName} ({$transaction->bank_code})",
            __('Type') => $transaction->isIncome() ? __('Money in') : __('Money out'),
            __('Original amount') => $transaction->currency ? Format::foreign($transaction->original_amount, $transaction->currency).($transaction->amount_estimated ? ' · '.__('estimated conversion') : '') : null,
            __('Counterparty') => $transaction->counterparty,
            __('Account number') => $transaction->counterparty_account,
            __('Description') => $transaction->description,
            __('Reference') => $transaction->reference,
        ] as $label => $value)
            <div class="flex justify-between gap-4 py-3">
                <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ $value ?? '—' }}</dd>
            </div>
        @endforeach
    </dl>

    @if($rawEmail)
        <section>
            <button type="button" wire:click="toggleRaw" aria-expanded="{{ $showRaw ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center justify-between font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                {{ __('View original email') }} <x-icon :name="$showRaw ? 'chevron-up' : 'chevron-down'" class="h-4 w-4 text-slate-500" />
            </button>
            @if($showRaw)
                <p class="mt-2 text-xs text-slate-500">{{ $rawEmail->subject }} · {{ Format::local($rawEmail->received_at)->format('d/m/Y H:i') }}</p>
                <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-white p-3 ring-1 ring-slate-200 font-mono text-xs">{{ $rawText }}</pre>
            @endif
        </section>
    @endif

    @unless($transaction->trashed())
        <button type="button" wire:click="toggleHidden" class="min-h-11 w-full rounded-xl px-4 text-sm font-medium text-slate-600 ring-1 ring-slate-300 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Hide transaction') }}</button>
        <p class="-mt-2 px-1 text-center text-xs text-slate-500">{{ __('Hidden transactions stay in the list but are not counted in totals or reports.') }}</p>
    @endunless
</div>
