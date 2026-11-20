@php use App\Support\Format; @endphp

<div class="space-y-4">
    <x-flash />

    @if($account?->status === \App\Enums\MailAccountStatus::NeedsReconnect)
        <a href="{{ route('connect') }}" wire:navigate class="block rounded-2xl bg-red-600 p-4 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
            <p class="flex items-center gap-2 font-semibold"><x-icon name="alert" class="h-4 w-4" /> {{ __('Gmail disconnected') }}</p>
            <p class="text-sm text-red-50">{{ __('The app password is no longer valid. Tap to reconnect.') }}</p>
        </a>
    @endif

    <div class="flex items-center justify-between gap-2">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Transactions') }}</h1>
        <div class="flex items-center gap-3">
            @if($account?->isSyncing())
                <p wire:poll.3s role="status" class="inline-flex min-h-11 items-center text-sm font-medium text-slate-500">
                    {{ __('Syncing') }}<span class="typing-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>
                </p>
            @endif
            <a href="{{ route('transactions.create') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 rounded-xl bg-black px-4 text-sm font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">+ {{ __('Add') }}</a>
        </div>
    </div>

    <x-month-switcher :month="$monthStart" />

    <section class="px-1" aria-labelledby="spent-heading">
        <h2 id="spent-heading" class="text-xs font-medium uppercase tracking-widest text-slate-500">{{ __('Spent') }}</h2>
        <p class="mt-1 whitespace-nowrap text-4xl font-normal tracking-tight font-num">{{ \Illuminate\Support\Str::beforeLast(Format::money($spent), ' ') }}<span class="text-slate-400"> ₫</span></p>

        <dl class="mt-3 grid grid-cols-2 gap-2 text-[13px]">
            <div>
                <dt class="text-xs text-slate-500">{{ __('Total income') }}</dt>
                <dd class="whitespace-nowrap font-medium font-num text-emerald-600">{{ Format::money($income, signed: true) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('Net') }}</dt>
                <dd @class(['whitespace-nowrap font-medium font-num', 'text-emerald-600' => $income >= $spent, 'text-red-600' => $income < $spent])>{{ Format::money($income - $spent, signed: true) }}</dd>
            </div>
        </dl>
    </section>

    @if($scopeLabel)
        <button type="button" wire:click="clearScope" aria-label="{{ __('Clear filter: :name', ['name' => $scopeLabel]) }}"
                class="inline-flex min-h-11 max-w-full items-center gap-2 rounded-full bg-white px-4 text-sm font-medium ring-1 ring-slate-300 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
            <span class="truncate">{{ $scopeLabel }}</span> <x-icon name="x" class="h-4 w-4 text-slate-500" />
        </button>
    @endif

    <div class="tabs" role="group" aria-label="{{ __('Filter transactions') }}">
        @foreach(['all' => __('All'), 'in' => __('Money in'), 'out' => __('Money out'), 'uncategorized' => __('Uncategorized')] as $value => $label)
            <button type="button" wire:click="setFilter('{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}" class="tab">{{ $label }}</button>
        @endforeach
    </div>

    @forelse($days as $date => $transactions)
        <section wire:key="day-{{ $date }}">
            @php($dayTotal = $transactions->reject->trashed()->sum->signedAmount())
            @php($day = Format::local($transactions->first()->occurred_at))
            <h2 class="mb-1 flex items-baseline justify-between gap-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <span>{{ match (true) {
                    $day->isToday() => __('Today'),
                    $day->isYesterday() => __('Yesterday'),
                    default => ucfirst($day->translatedFormat('l, d/m')),
                } }}</span>
                <span @class(['whitespace-nowrap font-num normal-case tracking-normal', 'text-emerald-600' => $dayTotal > 0])>{{ Format::money($dayTotal, signed: true) }}</span>
            </h2>
            <ul class="divide-y divide-slate-200 border-y border-slate-200">
                @foreach($transactions as $transaction)
                    <li wire:key="tx-{{ $transaction->id }}">
                        <a href="{{ route('transactions.show', [$transaction, 'filter' => $filter !== 'all' ? $filter : null, 'category' => $category, 'payee' => $payee ?: null]) }}" wire:navigate @class(['flex min-h-16 items-center gap-3 px-1 py-2 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500', 'opacity-50' => $transaction->trashed()])>
                            <x-category-icon :category="$transaction->category" class="h-10 w-10 text-xl" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $transaction->label() }}</span>
                                <span class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-xs text-slate-500">
                                    {{ Format::local($transaction->occurred_at)->format('H:i') }} ·
                                    @if($transaction->category)
                                        <span class="truncate">{{ $transaction->category->label }}</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">{{ __('Uncategorized') }}</span>
                                    @endif
                                    @if($transaction->trashed())
                                        <span class="rounded-full bg-slate-200 px-1.5 py-0.5 text-[11px] font-medium text-slate-700">{{ __('Hidden') }}</span>
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
        <div class="px-1 py-8 text-center">
            @if($hasAnyTransactions)
                <p class="font-semibold">{{ __('Nothing here') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('Try another month or clear the filter.') }}</p>
                @if($latestMonth)
                    <button type="button" wire:click="$set('month', '{{ $latestMonth }}')" class="mt-3 min-h-11 rounded-xl bg-black px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Go to :month', ['month' => \Carbon\CarbonImmutable::createFromFormat('!Y-m', $latestMonth)->format('m/Y')]) }}</button>
                @elseif($filter !== 'all')
                    <button type="button" wire:click="setFilter('all')" class="mt-3 min-h-11 rounded-xl bg-black px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Show all') }}</button>
                @endif
            @else
                <p class="font-semibold">{{ __('No transactions yet') }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ __('When your bank emails a balance change, the transaction shows up here. The first sync looks back :days days.', ['days' => config('services.mail_sync.first_sync_days')]) }}</p>
                @if(! $account)
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        <a href="{{ route('connect') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl bg-black px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Connect Gmail') }}</a>
                        <a href="{{ route('transactions.create') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Add transactions manually') }}</a>
                    </div>
                @endif
            @endif
        </div>
    @endforelse
</div>
