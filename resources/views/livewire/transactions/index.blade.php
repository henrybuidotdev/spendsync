@php use App\Support\Format; @endphp

<div class="space-y-4">
    <x-flash />

    @if($account?->status === \App\Enums\MailAccountStatus::NeedsReconnect)
        <a href="{{ route('connect') }}" wire:navigate class="block rounded-2xl bg-red-600 p-4 text-white shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
            <p class="font-semibold">⚠️ {{ __('Mất kết nối Gmail') }}</p>
            <p class="text-sm text-red-50">{{ __('Mật khẩu ứng dụng không còn hợp lệ. Chạm để kết nối lại.') }}</p>
        </a>
    @endif

    @if(session('syncing'))
        <div role="status" class="flex items-center gap-2 rounded-2xl bg-sky-50 p-4 text-sm text-sky-800">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" stroke-dasharray="40 20"/></svg>
            {{ __('Đang đồng bộ… Giao dịch mới sẽ hiện sau ít phút.') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-2">
        <h1 class="text-2xl font-bold">{{ __('Giao dịch') }}</h1>
        @if($account?->status === \App\Enums\MailAccountStatus::Active)
            <button type="button" wire:click="syncNow" wire:loading.attr="disabled"
                    class="inline-flex min-h-11 items-center gap-1 rounded-xl bg-white px-3 text-sm font-medium text-emerald-700 shadow-sm ring-1 ring-slate-200 hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                ↻ {{ __('Đồng bộ ngay') }}
            </button>
        @endif
    </div>

    <x-month-switcher :month="$monthStart" />

    <dl class="grid grid-cols-3 gap-2 text-center">
        <div class="rounded-2xl bg-white p-3 shadow-sm">
            <dt class="text-xs text-slate-500">{{ __('Chi') }}</dt>
            <dd class="mt-1 text-sm font-bold tabular-nums text-red-600">{{ Format::money($spent) }}</dd>
        </div>
        <div class="rounded-2xl bg-white p-3 shadow-sm">
            <dt class="text-xs text-slate-500">{{ __('Thu') }}</dt>
            <dd class="mt-1 text-sm font-bold tabular-nums text-emerald-600">{{ Format::money($income) }}</dd>
        </div>
        <div class="rounded-2xl bg-white p-3 shadow-sm">
            <dt class="text-xs text-slate-500">{{ __('Chênh lệch') }}</dt>
            <dd @class(['mt-1 text-sm font-bold tabular-nums', 'text-emerald-600' => $income >= $spent, 'text-red-600' => $income < $spent])>{{ Format::money($income - $spent, signed: true) }}</dd>
        </div>
    </dl>

    <div class="-mx-4 flex gap-2 overflow-x-auto px-4" role="group" aria-label="{{ __('Lọc giao dịch') }}">
        @foreach(['all' => __('Tất cả'), 'uncategorized' => __('Chưa phân loại'), 'in' => __('Tiền vào'), 'out' => __('Tiền ra')] as $value => $label)
            <button type="button" wire:click="setFilter('{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                    @class(['min-h-11 shrink-0 rounded-full px-4 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-emerald-600 text-white' => $filter === $value, 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-100' => $filter !== $value])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @forelse($days as $date => $transactions)
        <section wire:key="day-{{ $date }}">
            <h2 class="mb-1 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ ucfirst(Format::local($transactions->first()->occurred_at)->translatedFormat('l, d/m')) }}
            </h2>
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl bg-white shadow-sm">
                @foreach($transactions as $transaction)
                    <li wire:key="tx-{{ $transaction->id }}">
                        <a href="{{ route('transactions.show', $transaction) }}" wire:navigate class="flex min-h-16 items-center gap-3 px-3 py-2 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xl" aria-hidden="true">{{ $transaction->category?->icon ?? '❔' }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $transaction->label() }}</span>
                                <span class="flex items-center gap-2 text-xs text-slate-500">
                                    {{ Format::local($transaction->occurred_at)->format('H:i') }} · {{ $transaction->bank_code }}
                                    @if($transaction->category)
                                        · {{ $transaction->category->name }}
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800">{{ __('Chưa phân loại') }}</span>
                                    @endif
                                </span>
                            </span>
                            <x-amount :transaction="$transaction" class="text-sm" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm">
            <p class="text-4xl" aria-hidden="true">{{ $hasAnyTransactions ? '🗓️' : '📭' }}</p>
            @if($hasAnyTransactions)
                <p class="mt-2 font-semibold">{{ __('Không có giao dịch nào ở đây') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('Thử chọn tháng khác hoặc bỏ bộ lọc.') }}</p>
                @if($filter !== 'all')
                    <button type="button" wire:click="setFilter('all')" class="mt-3 min-h-11 rounded-xl bg-emerald-600 px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Xem tất cả') }}</button>
                @endif
            @else
                <p class="mt-2 font-semibold">{{ __('Chưa có giao dịch nào') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('Khi ngân hàng gửi email biến động số dư, giao dịch sẽ tự hiện ở đây. Lần đồng bộ đầu lấy 30 ngày gần nhất.') }}</p>
                @if(! $account)
                    <a href="{{ route('connect') }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-4 font-medium text-white">{{ __('Kết nối Gmail') }}</a>
                @endif
            @endif
        </div>
    @endforelse
</div>
