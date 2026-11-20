<div>
    <h2 id="banks-heading" class="font-semibold">{{ __('Supported banks') }}</h2>
    <ul class="mt-2 flex flex-wrap gap-2">
        @foreach(config('banks') as $code => $bank)
            <li class="rounded-full bg-white px-3 py-1.5 text-sm font-semibold ring-1 ring-slate-200">
                <span class="text-emerald-700">{{ $code }}</span> · {{ $bank['name'] }}
                <span class="font-normal text-slate-500">({{ count($bank['directions']) === 1 ? $bank['directions'][0]->label() : __('Money in & out') }})</span>
            </li>
        @endforeach
    </ul>
    <p class="mt-2 text-sm text-slate-600">
        {{ __('Need support for your bank or report a bug? Contact on Zalo') }}
        <a href="https://zalo.me/0869478154" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium text-emerald-700 underline">0869478154</a>
    </p>
    <p class="mt-2 text-sm text-slate-600">
        {{ __('No Gmail, or your bank is not yet supported?') }}
        <a href="{{ route('transactions.create') }}" wire:navigate class="inline-flex min-h-11 items-center font-medium text-emerald-700 underline">{{ __('Add transactions manually') }}</a>
    </p>
</div>
