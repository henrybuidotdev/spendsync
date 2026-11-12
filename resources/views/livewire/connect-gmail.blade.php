<div class="space-y-5">
    <x-flash />

    <div>
        <h1 class="text-2xl font-bold">{{ $reconnecting ? __('Kết nối lại Gmail') : __('Kết nối Gmail') }}</h1>
        <p class="mt-1 text-slate-600">{{ __('Mất khoảng 2 phút. Sau đó giao dịch ngân hàng sẽ tự động hiện ở đây.') }}</p>
    </div>

    <ol class="space-y-3">
        @foreach([
            [__('Bật xác minh 2 bước'), __('Google chỉ cho tạo mật khẩu ứng dụng khi tài khoản đã bật xác minh 2 bước.'), 'https://myaccount.google.com/signinoptions/two-step-verification', __('Mở cài đặt bảo mật'), '🔐'],
            [__('Mở trang Mật khẩu ứng dụng'), __('Đăng nhập Gmail của bạn nếu Google yêu cầu.'), 'https://myaccount.google.com/apppasswords', 'myaccount.google.com/apppasswords', '🌐'],
            [__('Tạo mật khẩu tên "ThuChi"'), __('Gõ ThuChi vào ô tên ứng dụng rồi bấm Tạo. Google hiện 16 chữ cái trong khung màu vàng.'), null, null, '✍️'],
            [__('Dán vào ô bên dưới'), __('Có hay không có dấu cách đều được.'), null, null, '📋'],
        ] as $index => [$heading, $body, $url, $linkText, $icon])
            <li class="flex gap-3 rounded-2xl bg-white p-4 shadow-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-black font-bold text-white" aria-hidden="true">{{ $index + 1 }}</span>
                <div class="min-w-0">
                    <p class="font-semibold"><span aria-hidden="true">{{ $icon }}</span> {{ $heading }}</p>
                    <p class="mt-0.5 text-sm text-slate-600">{{ $body }}</p>
                    @if($url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="mt-1 inline-flex min-h-11 items-center break-all text-sm font-medium text-emerald-700 underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ $linkText }} ↗</a>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <form wire:submit="connect" class="space-y-4 rounded-2xl bg-white p-4 shadow-sm">
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Địa chỉ Gmail') }}</label>
            <input wire:model="email" id="email" type="email" inputmode="email" autocomplete="email" placeholder="ban@gmail.com" required
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
