@php
    use App\Models\Category;
    use App\Support\Format;

    [$brandHead, $brandTail] = array_pad(\Illuminate\Support\Str::ucsplit(config('app.name')), 2, '');

    $category = fn (int $id, string $name, string $icon) => (new Category)->forceFill(['id' => $id, 'name' => $name, 'icon' => $icon]);

    $sampleTransactions = [
        ['Phở Thìn Lò Đúc', '12:04', $category(3, 'Food & drink', '🍜'), -85000],
        ['Highlands Coffee', '09:31', $category(1, 'Coffee', '☕'), -45000],
        ['CONG TY ABC', '08:00', $category(4, 'Salary', '💰'), 15000000],
        ['NGUYEN VAN A', '07:15', null, -262000],
    ];

    $dayTotal = array_sum(array_column($sampleTransactions, 3));

    $sampleReport = [
        [$category(3, 'Food & drink', '🍜'), 2150000],
        [$category(5, 'Shopping', '🛍️'), 1320000],
        [$category(6, 'Transport', '🛵'), 640000],
        [$category(1, 'Coffee', '☕'), 410000],
        [null, 262000],
    ];

    $reportTotal = array_sum(array_column($sampleReport, 1));

    $sampleDetail = [
        'payee' => 'Highlands Coffee',
        'amount' => -45000,
        'category' => $category(1, 'Coffee', '☕'),
        'choices' => [$category(1, 'Coffee', '☕'), $category(10, 'Restaurants', '🍽️'), $category(13, 'Delivery', '🥡')],
    ];

    $sampleRuleTexts = ['target' => '<em class="font-semibold not-italic">Highlands Coffee</em>', 'category' => e($sampleDetail['category']->label)];

    $sampleCategoryTree = [
        [$category(3, 'Food & drink', '🍜'), [$category(1, 'Coffee', '☕'), $category(10, 'Restaurants', '🍽️')]],
        [$category(6, 'Transport', '🛵'), [$category(12, 'Taxi', '🚕'), $category(15, 'Flights', '✈️')]],
        [$category(7, 'Bills', '🧾'), [$category(18, 'Electricity bill', '⚡'), $category(21, 'Internet bill', '🌐')]],
    ];

    $banks = collect(config('banks'))->pluck('name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="overflow-x-clip [scrollbar-gutter:stable]">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        @include('partials.sa')

        <div class="mx-auto max-w-lg px-4 pb-10 pt-6 lg:max-w-5xl lg:px-8">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" aria-label="{{ config('app.name') }}" class="inline-flex min-h-11 items-center gap-2.5 text-xl tracking-tighter text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950">
                    <img src="/icon.svg" alt="" class="h-8 w-8 rounded-[22%]">
                    <span aria-hidden="true"><span class="font-bold">{{ $brandHead }}</span><span class="font-normal">{{ $brandTail }}</span></span>
                </a>
                <nav class="flex items-center text-sm font-medium">
                    @auth
                        <a href="{{ route('transactions') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl bg-black px-4 text-sm font-medium text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('View transactions') }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    @else
                        <a href="{{ route('login') }}" class="mr-1 inline-flex min-h-11 items-center px-2.5 text-slate-700 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-xl bg-black px-4 text-sm font-medium text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Register') }}</a>
                    @endauth
                </nav>
            </header>

            <main>
                <div class="lg:mt-20 lg:grid lg:grid-cols-2 lg:items-start lg:gap-16">
                    <div>
                        <h1 class="mt-14 text-3xl font-bold leading-tight tracking-tight lg:mt-0 lg:text-5xl">{{ __('Automatically read your spending from bank emails.') }}</h1>
                        <p class="mt-4 leading-relaxed text-slate-700">{{ __('Connect Gmail once. Whenever your bank emails you about a transaction, SpendSync records the amount, the payee and the time.') }}</p>
                        <ul class="mt-5 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-700">
                            <li class="inline-flex items-center gap-1.5"><x-icon name="check" class="h-4 w-4 text-emerald-600" /> {{ __('Free & Open source') }}</li>
                            <li class="inline-flex items-center gap-1.5"><x-icon name="check" class="h-4 w-4 text-emerald-600" /> {{ __('Clean and simple UI') }}</li>
                            <li class="inline-flex items-center gap-1.5"><x-icon name="check" class="h-4 w-4 text-emerald-600" /> {{ __('Updates within minutes') }}</li>
                        </ul>
                        <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-3">
                            @auth
                                <a href="{{ route('transactions') }}" class="inline-flex min-h-12 items-center gap-1.5 rounded-xl bg-black px-5 font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('View transactions') }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                            @else
                                <a href="{{ route('register') }}" class="inline-flex min-h-12 items-center gap-1.5 rounded-xl bg-black px-5 font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Create an account') }} <x-icon name="arrow-right" class="h-4 w-4" /></a>
                                <p class="text-sm text-slate-700">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-medium text-emerald-700 underline hover:text-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Log in') }}</a></p>
                            @endauth
                        </div>
                    </div>

                    <div class="mt-12 lg:mt-0">
                        <div data-deck class="grid cursor-pointer pt-5">
                            <section aria-label="{{ __('Example transactions') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200 [grid-area:1/1]">
                                <h2 class="mb-3 px-1 text-lg font-semibold tracking-tight">{{ __('Transactions') }}</h2>
                                <h3 class="mb-1 flex items-baseline justify-between gap-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <span>{{ __('Today') }}</span>
                                    <span @class(['whitespace-nowrap font-num normal-case tracking-normal', 'text-emerald-600' => $dayTotal > 0])>{{ Format::money($dayTotal, signed: true) }}</span>
                                </h3>
                                <ul class="divide-y divide-slate-200 border-y border-slate-200">
                                    @foreach($sampleTransactions as [$payee, $time, $transactionCategory, $amount])
                                        <li class="flex min-h-16 items-center gap-3 px-1 py-2">
                                            <x-category-icon :category="$transactionCategory" class="h-10 w-10 text-xl" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate font-medium">{{ $payee }}</span>
                                                <span class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-xs text-slate-500">
                                                    {{ $time }} ·
                                                    @if($transactionCategory)
                                                        <span class="truncate">{{ $transactionCategory->label }}</span>
                                                    @else
                                                        <span class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">{{ __('Uncategorized') }}</span>
                                                    @endif
                                                </span>
                                            </span>
                                            <span @class(['whitespace-nowrap text-right text-sm font-semibold font-num', 'text-emerald-600' => $amount > 0])>{{ Format::money($amount, signed: true) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>

                            <section aria-label="{{ __('Example reports') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200 [grid-area:1/1]">
                                <h2 class="mb-3 px-1 text-lg font-semibold tracking-tight">{{ __('Reports') }}</h2>
                                <h3 class="flex items-baseline justify-between gap-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <span>{{ __('Spending by category') }}</span>
                                    <span class="whitespace-nowrap font-num normal-case tracking-normal">{{ Format::money($reportTotal) }}</span>
                                </h3>
                                <div class="mx-1 mt-3 flex h-2.5 gap-0.5 overflow-hidden rounded-full" role="presentation">
                                    @foreach($sampleReport as [$reportCategory, $amount])
                                        <div class="h-full" style="width: {{ 100 * $amount / $reportTotal }}%; background-color: rgb(var(--c-chart-{{ $reportCategory?->color ?? 8 }}))"></div>
                                    @endforeach
                                </div>
                                <ol class="mt-2 divide-y divide-slate-100">
                                    @foreach($sampleReport as [$reportCategory, $amount])
                                        <li class="flex items-center gap-2 px-1 py-2 text-sm">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: rgb(var(--c-chart-{{ $reportCategory?->color ?? 8 }}))" aria-hidden="true"></span>
                                            <x-category-icon :category="$reportCategory" class="h-7 w-7 text-base" />
                                            <span class="min-w-0 flex-1 truncate">{{ $reportCategory?->label ?? __('Uncategorized') }}</span>
                                            <span class="shrink-0 font-num text-slate-500">{{ round(100 * $amount / $reportTotal, 1) }}%</span>
                                            <span class="w-28 shrink-0 text-right font-semibold font-num">{{ Format::money($amount) }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </section>

                            <section aria-label="{{ __('Example transaction details') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200 [grid-area:1/1]">
                                <h2 class="mb-3 px-1 text-lg font-semibold tracking-tight">{{ __('Smart auto-categorize rules') }}</h2>
                                <div class="flex items-center gap-3 px-1">
                                    <x-category-icon :category="$sampleDetail['category']" class="h-10 w-10 text-xl" />
                                    <span class="min-w-0 flex-1 truncate font-medium">{{ $sampleDetail['payee'] }}</span>
                                    <span class="whitespace-nowrap text-sm font-semibold font-num">{{ Format::money($sampleDetail['amount'], signed: true) }}</span>
                                </div>
                                <div class="mt-5 flex flex-wrap gap-1.5 px-1 text-sm">
                                    @foreach($sampleDetail['choices'] as $choice)
                                        <span @class(['inline-flex items-center gap-1.5 rounded-full py-1 pl-1 pr-3 ring-1', 'bg-emerald-50 font-medium text-emerald-800 ring-emerald-600' => $choice->id === $sampleDetail['category']->id, 'text-slate-700 ring-slate-200' => $choice->id !== $sampleDetail['category']->id])>
                                            <x-category-icon :category="$choice" class="h-6 w-6 text-sm" />
                                            {{ $choice->label }}
                                        </span>
                                    @endforeach
                                </div>
                                <div class="mt-6 flex items-center gap-3 rounded-xl bg-amber-50 p-3 text-sm">
                                    <p class="flex-1">{!! __('Found :count "Uncategorized" transactions with :target. Set them to ":category" too?', ['count' => 3, ...$sampleRuleTexts]) !!}</p>
                                    <span class="inline-flex min-h-9 shrink-0 items-center rounded-xl bg-black px-3 text-sm font-semibold text-white">{{ __('OK, do it') }}</span>
                                </div>
                                <p class="mt-6 flex items-start gap-3 px-1 text-sm">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded bg-emerald-600 text-white" aria-hidden="true"><x-icon name="check" class="h-3.5 w-3.5" /></span>
                                    <span>{!! __('Automatically set ":category" for future transactions with :target', $sampleRuleTexts) !!}</span>
                                </p>
                            </section>

                            <section aria-label="{{ __('Example categories') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-200 [grid-area:1/1]">
                                <h2 class="mb-3 px-1 text-lg font-semibold tracking-tight">{{ __('Categories') }}</h2>
                                <ul class="space-y-1 text-sm">
                                    @foreach($sampleCategoryTree as [$parent, $children])
                                        <li>
                                            <span class="flex items-center gap-2 px-1 py-0.5 font-medium">
                                                <x-category-icon :category="$parent" class="h-7 w-7 text-base" />
                                                {{ $parent->label }}
                                            </span>
                                            <ul class="ml-[18px] border-l border-slate-200 pl-4">
                                                @foreach($children as $child)
                                                    <li class="flex items-center gap-2 py-0.5 text-slate-700">
                                                        <x-category-icon :category="$child" class="h-6 w-6 text-sm" />
                                                        {{ $child->label }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        </div>
                        <div data-deck-dots class="mt-4 flex justify-center gap-1.5" aria-hidden="true">
                            @foreach(range(1, 4) as $dot)
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-300 transition-all data-[active]:w-4 data-[active]:bg-slate-700"></span>
                            @endforeach
                        </div>
                        <button type="button" data-deck-next class="mx-auto mt-1 flex min-h-11 items-center gap-1.5 px-3 text-sm font-medium text-emerald-700 hover:text-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('See another example') }} <x-icon name="refresh" class="h-4 w-4" /></button>
                    </div>
                </div>

                <div class="lg:mt-24 lg:grid lg:grid-cols-2 lg:gap-16">
                    <section class="mt-14 lg:mt-0">
                        <h2 class="font-semibold">{{ __('How it works') }}</h2>
                        <ol class="mt-2 space-y-1 leading-relaxed text-slate-700">
                            @foreach([__('Create an account.'), __('Create an app password in your Google account and paste it into SpendSync.'), __('The transactions will be synced and show up within a few minutes.')] as $step)
                                <li>{{ $loop->iteration }}. {{ $step }}</li>
                            @endforeach
                        </ol>
                        <p class="mt-2 leading-relaxed text-slate-700">{{ __('No Gmail or your bank not yet supported? You can still add transactions manually.') }}</p>
                    </section>

                    <section class="mt-10 lg:mt-0">
                        <h2 class="font-semibold">{{ __('Supported banks') }}</h2>
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach($banks as $bank)
                                <li class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-white px-3.5 text-sm font-medium shadow-sm ring-1 ring-slate-200">
                                    <x-icon name="check" class="h-4 w-4 text-emerald-600" /> {{ $bank }}
                                </li>
                            @endforeach
                        </ul>
                        
                        <p class="mt-4 leading-relaxed text-slate-700">{{ __('Need support for your bank or report a bug? Contact us on Zalo') }}</p>
                        <img src="/qr.jpg" alt="{{ __('Zalo QR code') }}" width="520" height="520" loading="lazy" class="mt-4 h-32 w-32">
                    </section>
                </div>
            </main>

            <footer class="mt-14 lg:mt-24 text-center text-sm text-slate-500">
                <a href="{{ route('privacy') }}" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Privacy') }}</a>
                <span aria-hidden="true">&nbsp;·&nbsp;</span>
                <a href="{{ route('terms') }}" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Terms of use') }}</a>
                <span aria-hidden="true">&nbsp;·&nbsp;</span>
                <a href="{{ config('app.source_url') }}" target="_blank" rel="noopener" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Source on GitHub') }}</a>
                <span aria-hidden="true">&nbsp;·&nbsp;</span>
                @foreach(\App\Enums\Locale::cases() as $locale)
                    @continue($locale->value === app()->getLocale())
                    <a href="{{ route('locale', $locale) }}" lang="{{ $locale->value }}" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ $locale->label() }}</a>
                @endforeach
            </footer>
        </div>
    </body>
</html>
