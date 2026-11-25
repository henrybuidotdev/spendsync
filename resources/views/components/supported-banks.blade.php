<div>
    <h2 id="banks-heading" class="font-semibold">{{ __('Supported banks') }}</h2>
    <ul class="mt-2 flex flex-wrap gap-2">
        @foreach(config('banks') as $code => $bank)
            <li class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-white px-3.5 text-sm font-medium shadow-sm ring-1 ring-slate-200">
                <x-icon name="check" class="h-4 w-4 text-emerald-600" /> {{ $bank['name'] }}
            </li>
        @endforeach
    </ul>

    <p class="text-sm mt-4 leading-relaxed text-slate-700">{{ __('Need support for your bank or report a bug? Contact us on Zalo') }}</p>    
    <img src="/qr.jpg" alt="{{ __('Zalo QR code') }}" width="520" height="520" loading="lazy" class="h-32 w-32 mt-4">
</div>
