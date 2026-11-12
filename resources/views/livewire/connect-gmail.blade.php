<div class="space-y-5">
    <x-flash />

    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $reconnecting ? __('Kết nối lại Gmail') : __('Kết nối Gmail') }}</h1>
        <p class="mt-1 text-slate-600">{{ __('Mất khoảng 2 phút. Sau đó giao dịch ngân hàng sẽ tự động hiện ở đây.') }}</p>
    </div>

    <section class="rounded-2xl bg-white p-4 shadow-sm">
        <ol class="space-y-3">
            @foreach([
                [__('Bước 1: Bật xác minh 2 bước cho tài khoản Google.'), 'https://myaccount.google.com/signinoptions/two-step-verification', __('Mở cài đặt bảo mật')],
                [__('Bước 2: Mở trang Mật khẩu ứng dụng.'), 'https://myaccount.google.com/apppasswords', 'myaccount.google.com/apppasswords'],
                [__('Bước 3: Tạo mật khẩu tên BeanPulse. Google sẽ hiện 16 chữ cái.'), null, null],
                [__('Bước 4: Dán 16 chữ cái đó vào ô bên dưới.'), null, null],
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
                <label for="email" class="block text-sm font-medium">{{ __('Địa chỉ email') }}</label>
                <input wire:model="email" id="email" type="email" inputmode="email" autocomplete="email" placeholder="example@gmail.com" required
                       class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="appPassword" class="block text-sm font-medium">{{ __('Mật khẩu ứng dụng (16 chữ cái)') }}</label>
                <input wire:model="appPassword" id="appPassword" type="password" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="xxxx xxxx xxxx xxxx" required
                       class="mt-1 block min-h-12 w-full rounded-xl border-slate-300 font-mono text-base tracking-wider focus:border-emerald-500 focus:ring-emerald-500">
                @error('appPassword') <p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-black px-4 font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:opacity-60">
                <span wire:loading.remove wire:target="connect">{{ __('Kết nối') }}</span>
                <span class="hidden" wire:loading.inline wire:target="connect">{{ __('Đang kiểm tra…') }}</span>
            </button>
        </form>
    </section>

    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        <p class="font-semibold">🛡️ {{ __('An toàn & riêng tư') }}</p>
        <p class="mt-1">{{ __('Chúng tôi chỉ đọc email từ ngân hàng. Mật khẩu ứng dụng được mã hóa. Bạn có thể thu hồi bất cứ lúc nào tại Google.') }}</p>
        <p class="mt-2 flex flex-wrap gap-x-4">
            <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium underline">{{ __('Cách thu hồi') }} ↗</a>
            <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center font-medium underline" wire:navigate>{{ __('Chính sách quyền riêng tư') }}</a>
        </p>
    </div>

    <div>
        <p class="text-sm font-medium text-slate-600">{{ __('Ngân hàng được hỗ trợ') }}</p>
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach($banks as $code => $bank)
                <li class="rounded-full bg-white px-3 py-1.5 text-sm font-semibold shadow-sm ring-1 ring-slate-200">
                    <span class="text-emerald-700">{{ $code }}</span> · {{ $bank['name'] }}
                </li>
            @endforeach
        </ul>
    </div>
</div>
