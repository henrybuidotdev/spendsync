@php use App\Support\Format; @endphp

<div class="space-y-4">
    <h1 class="text-2xl font-bold">{{ __('Báo cáo') }}</h1>

    <x-month-switcher :month="$monthStart" />

    @if($total === 0)
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm">
            <p class="text-4xl" aria-hidden="true">📊</p>
            <p class="mt-2 font-semibold">{{ __('Chưa có khoản chi nào trong tháng này') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('Thử chọn tháng trước, hoặc xem danh sách giao dịch.') }}</p>
            <a href="{{ route('transactions') }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Xem giao dịch') }}</a>
        </div>
    @else
        <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="by-category">
            <div class="flex items-baseline justify-between">
                <h2 id="by-category" class="font-semibold">{{ __('Chi theo danh mục') }}</h2>
                <p class="font-bold tabular-nums text-red-600">{{ Format::money($total) }}</p>
            </div>

            <ol class="mt-4 space-y-3">
                @foreach($byCategory as $row)
                    @php($percent = round(100 * $row->total / $total, 1))
                    <li>
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="flex min-w-0 items-center gap-2"><x-category-icon :icon="$row->icon" class="h-7 w-7 text-base" /><span class="truncate">{{ $row->name }} <span class="text-slate-400">· {{ $row->count }}</span></span></span>
                            <span class="shrink-0 tabular-nums"><span class="font-semibold">{{ Format::money($row->total) }}</span> <span class="text-slate-500">{{ $percent }}%</span></span>
                        </div>
                        <div class="mt-1 h-2.5 overflow-hidden rounded-full bg-slate-100" role="presentation">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ max($percent, 1) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        @if($topCounterparties->isNotEmpty())
            <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="top-counterparties">
                <h2 id="top-counterparties" class="font-semibold">{{ __('Chi nhiều nhất cho') }}</h2>
                <ol class="mt-2 divide-y divide-slate-100">
                    @foreach($topCounterparties as $index => $row)
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 font-semibold text-slate-600">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate font-medium">{{ $row->counterparty }}</span>
                            <span class="shrink-0 font-semibold tabular-nums">{{ Format::money((int) $row->total) }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    @endif
</div>
