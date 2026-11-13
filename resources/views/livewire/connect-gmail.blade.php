<div class="space-y-5">
    <x-flash />

    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $reconnecting ? __('Reconnect Gmail') : __('Connect Gmail') }}</h1>
        <p class="mt-1 text-slate-600">{{ __('Takes about 2 minutes. After that your bank transactions show up automatically.') }}</p>
    </div>

    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <ol class="space-y-3">
            @foreach([
                [__('Step 1: Turn on 2-Step Verification for your Google account. Google only lets you create App passwords when it is on.'), 'https://myaccount.google.com/signinoptions/two-step-verification', __('Open security settings')],
                [__('Step 2: Open the App passwords page and create a password named SpendSync. Google shows 16 letters.'), 'https://myaccount.google.com/apppasswords', 'myaccount.google.com/apppasswords'],
                [__('Step 3: Paste those 16 letters below.'), null, null],
            ] as [$step, $url, $linkText])
                <li>
                    <p>{{ $step }}</p>
                    @if($url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center break-all text-sm font-medium text-emerald-700 underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ $linkText }} ↗</a>
                    @endif
                </li>
            @endforeach
        </ol>

        <form wire:submit="connect" class="mt-5 space-y-4 pt-5">
            <div>
                <label for="email" class="block text-sm font-medium">{{ __('Email address') }}</label>
                <input wire:model="email" id="email" type="email" inputmode="email" autocomplete="email" placeholder="example@gmail.com" required
                       class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="appPassword" class="block text-sm font-medium">{{ __('App password (16 letters)') }}</label>
                <input wire:model="appPassword" id="appPassword" type="password" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="xxxx xxxx xxxx xxxx" required
                       class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 font-mono text-base tracking-wider focus:border-emerald-500 focus:ring-emerald-500">
                @error('appPassword') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-black px-4 font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:opacity-60">
                <span wire:loading.remove wire:target="connect">{{ __('Connect') }}</span>
                <span class="hidden" wire:loading.inline wire:target="connect">{{ __('Checking…') }}</span>
            </button>
        </form>
    </section>

    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        <p class="flex items-center gap-2 font-semibold"><x-icon name="shield" class="h-4 w-4" /> {{ __('Safe & private') }}</p>
        <p class="mt-1">{{ __('We only read emails from banks. The app password is encrypted. You can revoke it at any time in Google.') }}</p>
        <p class="mt-2 flex flex-wrap gap-x-4">
            <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium underline">{{ __('How to revoke') }} ↗</a>
            <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium underline">{{ __('Privacy policy') }}</a>
            <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium underline">{{ __('Terms of use') }}</a>
        </p>
    </div>

    <p class="text-sm text-slate-600">
        {{ __('No Gmail, or your bank is not supported?') }}
        <a href="{{ route('transactions.create') }}" wire:navigate class="inline-flex min-h-11 items-center font-medium text-emerald-700 underline">{{ __('Add transactions manually') }}</a>
    </p>

    <div>
        <p class="text-sm font-medium text-slate-600">{{ __('Supported banks') }}</p>
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach($banks as $code => $bank)
                <li class="rounded-full bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-slate-200">
                    <span class="text-emerald-700">{{ $code }}</span> · {{ $bank['name'] }}
                </li>
            @endforeach
        </ul>
    </div>
</div>
