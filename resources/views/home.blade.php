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

    $banks = collect(config('banks'))->pluck('name')->join(', ', ' '.__('and').' ');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="[scrollbar-gutter:stable]">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="mx-auto max-w-lg px-4 pb-10 pt-6">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" aria-label="{{ config('app.name') }}" class="inline-flex min-h-11 items-center gap-2.5 text-xl tracking-tighter text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950">
                    <img src="/icon.svg" alt="" class="h-8 w-8 rounded-[22%]">
                    <span aria-hidden="true"><span class="font-bold">{{ $brandHead }}</span><span class="font-normal">{{ $brandTail }}</span></span>
                </a>
                <nav class="flex items-center text-sm font-medium">
                    @auth
                        <a href="{{ route('transactions') }}" class="inline-flex min-h-9 items-center rounded-xl bg-black px-3 text-xs font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('View transactions') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex min-h-9 items-center rounded-xl bg-black px-3 text-xs font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="ml-1 inline-flex min-h-11 items-center px-2.5 text-slate-700 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Register') }}</a>
                    @endauth
                </nav>
            </header>

            <main>
                <h1 class="mt-14 text-3xl font-bold leading-tight tracking-tight">{{ __('Automatically read your spending from bank emails.') }}</h1>
                <p class="mt-4 leading-relaxed text-slate-700">{{ __('Connect Gmail once. Whenever your bank emails you about a transaction, SpendSync records the amount, the payee and the time. You only need to categorize at the end of the day.') }}</p>

                <section aria-label="{{ __('Example transactions') }}" class="mt-12">
                    <h2 class="mb-1 flex items-baseline justify-between gap-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <span>{{ __('Today') }}</span>
                        <span @class(['whitespace-nowrap font-num normal-case tracking-normal', 'text-emerald-600' => $dayTotal > 0])>{{ Format::money($dayTotal, signed: true) }}</span>
                    </h2>
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

                <section class="mt-14">
                    <h2 class="font-semibold">{{ __('How it works') }}</h2>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 leading-relaxed text-slate-700">
                        <li>{{ __('Create an account.') }}</li>
                        <li>{{ __('Create an app password in your Google account and paste it into SpendSync.') }}</li>
                        <li>{{ __('New transactions show up on their own within a few minutes.') }}</li>
                    </ol>
                    <p class="mt-2 leading-relaxed text-slate-700">{{ __('No Gmail? You can still add transactions manually.') }}</p>
                </section>

                <section class="mt-10">
                    <h2 class="font-semibold">{{ __('Supported banks') }}</h2>
                    <p class="mt-2 leading-relaxed text-slate-700">
                        @if(config('services.zalo.phone'))
                            {{ __(':banks. Need another bank? Message us on Zalo', ['banks' => $banks]) }}
                            <a href="https://zalo.me/{{ config('services.zalo.phone') }}" target="_blank" rel="noopener" class="font-medium text-emerald-700 underline">{{ config('services.zalo.phone') }}</a>.
                        @else
                            {{ $banks }}.
                        @endif
                    </p>
                </section>
            </main>

            <footer class="mt-14 text-center text-sm text-slate-500">
                <a href="{{ route('privacy') }}" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Privacy') }}</a>
                <span aria-hidden="true">&nbsp;·&nbsp;</span>
                <a href="{{ route('terms') }}" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Terms of use') }}</a>
                <span aria-hidden="true">&nbsp;·&nbsp;</span>
                @foreach(\App\Enums\Locale::cases() as $locale)
                    @continue($locale->value === app()->getLocale())
                    <a href="{{ route('locale', $locale) }}" lang="{{ $locale->value }}" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ $locale->label() }}</a>
                @endforeach
            </footer>
        </div>
    </body>
</html>
