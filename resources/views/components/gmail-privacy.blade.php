<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
    <p class="flex items-center gap-2 font-semibold"><x-icon name="shield" class="h-4 w-4" /> {{ __('Safe & private') }}</p>
    <p class="mt-1">{{ __('We only read emails from banks. The app password is encrypted. You can revoke it at any time in Google.') }}</p>
    <p class="mt-2 flex flex-wrap gap-x-4">
        <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium underline">{{ __('How to revoke') }} ↗</a>
        <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium underline">{{ __('Privacy policy') }}</a>
        <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium underline">{{ __('Terms of use') }}</a>
    </p>
</div>
