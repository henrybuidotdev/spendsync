<div class="space-y-5">
    <a href="{{ route('transactions') }}" wire:navigate class="-ml-1 inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"><x-icon name="chevron-left" class="h-4 w-4" /> {{ __('Transactions') }}</a>

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

    <x-supported-banks />

    <x-gmail-privacy />
</div>
