@php
    $banks = collect(config('banks'))->pluck('name')->join(', ');

    $sections = [
        'Chúng tôi đọc gì' => [
            "Chỉ email gửi từ địa chỉ của các ngân hàng được hỗ trợ ({$banks}). Bộ lọc người gửi chạy ngay trên máy chủ Gmail, nên email khác không bao giờ được tải về.",
            'Chỉ đọc. Ứng dụng không đánh dấu đã đọc, không di chuyển, không xóa và không gửi email nào.',
        ],
        'Chúng tôi lưu gì' => [
            'Địa chỉ Gmail và mật khẩu ứng dụng của bạn. Mật khẩu ứng dụng được mã hóa và không bao giờ được hiển thị hay ghi vào nhật ký.',
            'Nội dung các email ngân hàng đó và giao dịch đọc được từ chúng: số tiền, thời gian, người nhận/gửi, nội dung chuyển khoản, số dư.',
            'Danh mục, quy tắc và ghi chú do bạn tạo.',
            'Chúng tôi không bán hay chia sẻ dữ liệu của bạn với ai.',
        ],
        'Cách ngắt kết nối và xóa dữ liệu' => [
            'Ngắt kết nối: Cài đặt → Ngắt kết nối. Mật khẩu ứng dụng bị xóa ngay, giao dịch cũ vẫn giữ.',
            'Thu hồi tại Google: vào myaccount.google.com/apppasswords và xóa mật khẩu "SpendSync". Ứng dụng sẽ mất quyền đọc ngay lập tức.',
            'Xóa dữ liệu: Cài đặt → Vùng nguy hiểm → Xóa dữ liệu. Email, giao dịch, danh mục, quy tắc và kết nối Gmail bị xóa vĩnh viễn; tài khoản đăng nhập được giữ lại.',
            'Xóa tài khoản: Cài đặt → Vùng nguy hiểm → Xóa tài khoản. Tài khoản đăng nhập cùng toàn bộ dữ liệu bị xóa vĩnh viễn.',
        ],
    ];
@endphp

<x-privacy-layout title="Quyền riêng tư" :sections="$sections">
    <x-slot:intro>
        <p class="text-slate-600">Ngắn gọn, dễ hiểu: chúng tôi đọc gì, lưu gì và cách bạn xóa hết.</p>
    </x-slot:intro>

    <p class="text-sm">
        <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-medium text-emerald-700 underline">Thu hồi mật khẩu ứng dụng tại Google ↗</a>
    </p>
</x-privacy-layout>
