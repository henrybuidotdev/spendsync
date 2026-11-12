@php use App\Support\Format; @endphp

<div class="space-y-4">
    <x-flash />

    <a href="{{ route('transactions', ['month' => Format::local($transaction->occurred_at)->format('Y-m')]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">‹ {{ __('Giao dịch') }}</a>

    <div class="rounded-2xl bg-white p-5 text-center shadow-sm">
        <x-category-icon :category="$transaction->category" class="mx-auto h-16 w-16 text-4xl" />
        <p class="mt-2 break-words font-semibold">{{ $transaction->label() }}</p>
        <x-amount :transaction="$transaction" class="mt-1 block text-3xl" />
        <p class="mt-1 text-sm text-slate-500">{{ ucfirst(Format::local($transaction->occurred_at)->translatedFormat('l, d/m/Y · H:i')) }}</p>
    </div>

    <dl class="divide-y divide-slate-100 rounded-2xl bg-white px-4 text-sm shadow-sm">
        @foreach([
            __('Ngân hàng') => "{$bankName} ({$transaction->bank_code})",
            __('Loại') => $transaction->isIncome() ? __('Tiền vào') : __('Tiền ra'),
            __('Người nhận / gửi') => $transaction->counterparty,
            __('Số tài khoản') => $transaction->counterparty_account,
            __('Nội dung') => $transaction->description,
            __('Số dư sau giao dịch') => $transaction->balance_after !== null ? Format::money($transaction->balance_after) : null,
            __('Mã tham chiếu') => $transaction->reference,
        ] as $label => $value)
            <div class="flex justify-between gap-4 py-3">
                <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                <dd class="min-w-0 break-words text-right font-medium">{{ $value ?? '—' }}</dd>
            </div>
        @endforeach
    </dl>

    <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="category-heading">
        <h2 id="category-heading" class="font-semibold">{{ __('Danh mục') }}</h2>

        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
            @foreach($categories as $category)
                <button type="button" wire:click="setCategory({{ $category->id }})" wire:key="cat-{{ $category->id }}"
                        aria-pressed="{{ $transaction->category_id === $category->id ? 'true' : 'false' }}"
                        @class(['flex min-h-16 flex-col items-center justify-center rounded-xl p-2 text-xs font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-black text-white' => $transaction->category_id === $category->id, 'bg-slate-50 ring-1 ring-slate-200 hover:bg-emerald-50' => $transaction->category_id !== $category->id])>
                    <x-category-icon :category="$category" class="h-8 w-8 text-xl" />
                    <span class="mt-1 line-clamp-2 text-center">{{ $category->name }}</span>
                </button>
            @endforeach
        </div>

        @if($this->ruleTarget())
            <label class="mt-3 flex min-h-11 cursor-pointer items-start gap-3 text-sm">
                <input type="checkbox" wire:model="applyToFuture" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>{{ __('Áp dụng cho các giao dịch sau với') }} <em class="font-semibold not-italic">{{ \Illuminate\Support\Str::limit($this->ruleTarget(), 60) }}</em></span>
            </label>
        @endif
    </section>

    <form wire:submit="saveNote" class="rounded-2xl bg-white p-4 shadow-sm">
        <label for="note" class="font-semibold">{{ __('Ghi chú') }}</label>
        <textarea wire:model="note" id="note" rows="2" maxlength="1000" placeholder="{{ __('Ví dụ: ăn trưa với team') }}"
                  class="mt-2 block w-full rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500"></textarea>
        @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        <button type="submit" class="mt-2 min-h-11 rounded-xl bg-slate-800 px-4 text-sm font-medium text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Lưu ghi chú') }}</button>
    </form>

    @if($rawEmail)
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <button type="button" wire:click="toggleRaw" aria-expanded="{{ $showRaw ? 'true' : 'false' }}" class="flex min-h-11 w-full items-center justify-between font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                {{ __('Xem email gốc') }} <span aria-hidden="true">{{ $showRaw ? '▴' : '▾' }}</span>
            </button>
            @if($showRaw)
                <p class="mt-2 text-xs text-slate-500">{{ $rawEmail->subject }} · {{ Format::local($rawEmail->received_at)->format('d/m/Y H:i') }}</p>
                <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-3 font-mono text-xs">{{ $rawText }}</pre>
            @endif
        </section>
    @endif
</div>
