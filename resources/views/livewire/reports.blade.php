@php use App\Support\Format; @endphp

<div class="space-y-4">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Báo cáo') }}</h1>

    <x-month-switcher :month="$monthStart" />

    @if($total === 0)
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm">
            <p class="text-4xl" aria-hidden="true">📊</p>
            <p class="mt-2 font-semibold">{{ __('Chưa có khoản chi nào trong tháng này') }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ __('Thử chọn tháng trước, hoặc xem danh sách giao dịch.') }}</p>
            <a href="{{ route('transactions') }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-black px-4 font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Xem giao dịch') }}</a>
        </div>
    @else
        <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="by-category">
            <div class="flex items-baseline justify-between">
                <h2 id="by-category" class="font-semibold">{{ __('Chi theo danh mục') }}</h2>
                <p class="font-semibold tabular-nums">{{ Format::money($total) }}</p>
            </div>

            <div class="mt-3 flex h-2.5 gap-0.5 overflow-hidden rounded-full" role="presentation">
                @foreach($byCategory as $row)
                    <div class="h-full" style="width: {{ 100 * $row->total / $total }}%; background-color: rgb(var(--c-chart-{{ $loop->index % 8 + 1 }}))"></div>
                @endforeach
            </div>

            <ol class="mt-2 divide-y divide-slate-100">
                @foreach($byCategory as $row)
                    <li class="flex items-center gap-2 py-2.5 text-sm">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: rgb(var(--c-chart-{{ $loop->index % 8 + 1 }}))" aria-hidden="true"></span>
                        <x-category-icon :category="$row->category" class="h-7 w-7 text-base" />
                        <span class="min-w-0 flex-1 truncate">{{ $row->name }} <span class="text-slate-400">· {{ $row->count }}</span></span>
                        <span class="shrink-0 tabular-nums text-slate-500">{{ round(100 * $row->total / $total, 1) }}%</span>
                        <span class="w-28 shrink-0 text-right font-semibold tabular-nums">{{ Format::money($row->total) }}</span>
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
