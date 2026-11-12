<!DOCTYPE html>
<html lang="vi">
    <head>
        @include('partials.head', ['title' => 'Quyền riêng tư'])
    </head>
    <body class="bg-slate-100 font-sans text-slate-900 antialiased">
        <main class="mx-auto max-w-md space-y-4 px-4 py-6">
            <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">‹ {{ config('app.name') }}</a>
            <h1 class="text-2xl font-semibold tracking-tight">Quyền riêng tư</h1>
            <p class="text-slate-600">Ngắn gọn, dễ hiểu: chúng tôi đọc gì, lưu gì và cách bạn xóa hết.</p>

            @foreach([
                ['📬 Chúng tôi đọc gì', [
                    'Chỉ email gửi từ địa chỉ của các ngân hàng được hỗ trợ ('.collect(config('banks'))->pluck('name')->join(', ').'). Bộ lọc người gửi chạy ngay trên máy chủ Gmail, nên email khác không bao giờ được tải về.',
                    'Chỉ đọc. Ứng dụng không đánh dấu đã đọc, không di chuyển, không xóa và không gửi email nào.',
                ]],
                ['💾 Chúng tôi lưu gì', [
                    'Địa chỉ Gmail và mật khẩu ứng dụng của bạn. Mật khẩu ứng dụng được mã hóa và không bao giờ được hiển thị hay ghi vào nhật ký.',
                    'Nội dung các email ngân hàng đó và giao dịch đọc được từ chúng: số tiền, thời gian, người nhận/gửi, nội dung chuyển khoản, số dư.',
                    'Danh mục, quy tắc và ghi chú do bạn tạo.',
                    'Chúng tôi không bán hay chia sẻ dữ liệu của bạn với ai.',
                ]],
                ['🗑️ Cách xóa', [
                    'Ngắt kết nối: Cài đặt → Ngắt kết nối. Mật khẩu ứng dụng bị xóa ngay, giao dịch cũ vẫn giữ.',
                    'Thu hồi tại Google: vào myaccount.google.com/apppasswords và xóa mật khẩu "ThuChi". Ứng dụng sẽ mất quyền đọc ngay lập tức.',
                    'Xóa dữ liệu: Cài đặt → Vùng nguy hiểm → Xóa dữ liệu. Email, giao dịch, danh mục, quy tắc và kết nối Gmail bị xóa vĩnh viễn; tài khoản đăng nhập được giữ lại.',
                    'Xóa tài khoản: Cài đặt → Vùng nguy hiểm → Xóa tài khoản. Tài khoản đăng nhập cùng toàn bộ dữ liệu bị xóa vĩnh viễn.',
                ]],
            ] as [$heading, $items])
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">{{ $heading }}</h2>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-slate-700">
                        @foreach($items as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            <p class="text-sm">
                <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium text-emerald-700 underline">Thu hồi mật khẩu ứng dụng tại Google ↗</a>
            </p>
        </main>
    </body>
</html>
