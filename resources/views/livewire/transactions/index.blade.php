@php use App\Support\Format; @endphp

<div class="space-y-4">
    <x-flash />

    @if($account?->status === \App\Enums\MailAccountStatus::NeedsReconnect)
        <a href="{{ route('connect') }}" wire:navigate class="block rounded-2xl bg-red-600 p-4 text-white shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
            <p class="font-semibold">⚠️ {{ __('Mất kết nối Gmail') }}</p>
            <p class="text-sm text-red-50">{{ __('Mật khẩu ứng dụng không còn hợp lệ. Chạm để kết nối lại.') }}</p>
        </a>
    @endif

    <div class="flex items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Giao dịch') }}</h1>
        @if($account?->status === \App\Enums\MailAccountStatus::Active)
            @if($account->isSyncing())
                <button type="button" disabled wire:poll.3s role="status"
                        class="inline-flex min-h-11 cursor-not-allowed items-center rounded-xl bg-white px-3 text-sm font-medium text-slate-500 shadow-sm ring-1 ring-slate-200">
                    {{ __('Đang đồng bộ') }}<span class="typing-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>
                </button>
            @else
                <button type="button" wire:click="syncNow" wire:loading.attr="disabled"
                        class="inline-flex min-h-11 items-center gap-1 rounded-xl bg-white px-3 text-sm font-medium text-emerald-700 shadow-sm ring-1 ring-slate-200 hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-60">
                    ↻ {{ __('Đồng bộ ngay') }}
                </button>
            @endif
        @endif
    </div>

    <x-month-switcher :month="$monthStart" />

    <section class="px-1" aria-labelledby="spent-heading">
        <h2 id="spent-heading" class="text-xs font-medium uppercase tracking-widest text-slate-500">{{ __('Đã chi') }}</h2>
        <p class="mt-1 whitespace-nowrap text-4xl font-normal tracking-tight tabular-nums">{{ \Illuminate\Support\Str::beforeLast(Format::money($spent), ' ') }}<span class="text-slate-400"> ₫</span></p>

        <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
            <div>
                <dt class="text-xs text-slate-500">{{ __('Thu') }}</dt>
                <dd class="whitespace-nowrap font-medium tabular-nums text-emerald-600">{{ Format::money($income, signed: true) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Chi') }}</dt>
                <dd class="whitespace-nowrap font-medium tabular-nums">{{ Format::money($spent) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Chênh lệch') }}</dt>
                <dd @class(['whitespace-nowrap font-medium tabular-nums', 'text-emerald-600' => $income >= $spent, 'text-red-600' => $income < $spent])>{{ Format::money($income - $spent, signed: true) }}</dd>
            </div>
        </dl>
    </section>

    <div class="flex gap-1 overflow-x-auto rounded-full bg-slate-100 p-1" role="group" aria-label="{{ __('Lọc giao dịch') }}">
        @foreach(['all' => __('Tất cả'), 'uncategorized' => __('Chưa phân loại'), 'in' => __('Tiền vào'), 'out' => __('Tiền ra')] as $value => $label)
            <button type="button" wire:click="setFilter('{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                    @class(['min-h-11 flex-1 shrink-0 whitespace-nowrap rounded-full px-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-white text-slate-900 shadow-sm' => $filter === $value, 'text-slate-600 hover:text-slate-900' => $filter !== $value])>
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
                        <a href="{{ route('transactions.show', [$transaction, 'filter' => $filter !== 'all' ? $filter : null]) }}" wire:navigate class="flex min-h-16 items-center gap-3 px-3 py-2 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500">
                            <x-category-icon :category="$transaction->category" class="h-10 w-10 text-xl" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $transaction->label() }}</span>
                                <span class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-xs text-slate-500">
                                    {{ Format::local($transaction->occurred_at)->format('H:i') }} ·
                                    @if($transaction->category)
                                        <span class="truncate">{{ $transaction->category->name }}</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">{{ __('Chưa phân loại') }}</span>
                                    @endif
                                </span>
                            </span>
                            <x-amount :transaction="$transaction" class="text-right text-sm" />
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
                @if($latestMonth)
                    <button type="button" wire:click="$set('month', '{{ $latestMonth }}')" class="mt-3 min-h-11 rounded-xl bg-black px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Xem tháng :month', ['month' => \Carbon\CarbonImmutable::createFromFormat('!Y-m', $latestMonth)->format('m/Y')]) }}</button>
                @elseif($filter !== 'all')
                    <button type="button" wire:click="setFilter('all')" class="mt-3 min-h-11 rounded-xl bg-black px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Xem tất cả') }}</button>
                @endif
            @else
                <p class="mt-2 font-semibold">{{ __('Chưa có giao dịch nào') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('Khi ngân hàng gửi email biến động số dư, giao dịch sẽ tự hiện ở đây. Lần đồng bộ đầu lấy 90 ngày gần nhất.') }}</p>
                @if(! $account)
                    <a href="{{ route('connect') }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-black px-4 font-medium text-white">{{ __('Kết nối Gmail') }}</a>
                @endif
            @endif
        </div>
    @endforelse
</div>
