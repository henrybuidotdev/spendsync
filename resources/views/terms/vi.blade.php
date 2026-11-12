@php
    $sections = [
        'SpendSync làm gì' => [
            'SpendSync đọc email biến động số dư từ các ngân hàng được hỗ trợ trong hộp thư Gmail mà bạn kết nối, rồi ghi lại thành giao dịch để bạn phân loại và xem báo cáo.',
            'Bạn kết nối bằng mật khẩu ứng dụng của Google. Ứng dụng chỉ đọc, không gửi, không xóa và không thay đổi email nào.',
        ],
        'Tài khoản của bạn' => [
            'Chỉ kết nối hộp thư Gmail của chính bạn, hoặc hộp thư bạn được phép sử dụng.',
            'Bạn tự giữ an toàn cho mật khẩu đăng nhập và mật khẩu ứng dụng. Nếu nghi ngờ bị lộ, hãy đổi mật khẩu và thu hồi mật khẩu ứng dụng tại Google ngay.',
        ],
        'Số liệu chỉ để tham khảo' => [
            'Giao dịch được đọc tự động từ email nên có thể sai hoặc thiếu, ví dụ khi ngân hàng đổi mẫu email hoặc khi quy đổi ngoại tệ là ước tính.',
            'Hãy đối chiếu với sao kê ngân hàng trước khi dựa vào số liệu cho việc quan trọng.',
            'SpendSync không phải là lời khuyên tài chính, thuế hay kế toán.',
        ],
        'Những điều không được làm' => [
            'Truy cập hoặc cố truy cập dữ liệu của người khác.',
            'Làm quá tải, dò quét hoặc can thiệp vào hệ thống.',
            'Dùng ứng dụng cho mục đích trái pháp luật.',
        ],
        'Ngừng sử dụng' => [
            'Bạn có thể ngắt kết nối Gmail, xóa dữ liệu hoặc xóa tài khoản bất cứ lúc nào trong Cài đặt. Chi tiết xem trang Quyền riêng tư.',
            'Chúng tôi có thể tạm khóa tài khoản vi phạm các điều khoản này, hoặc ngừng dịch vụ. Khi có thể, chúng tôi sẽ báo trước để bạn kịp xóa dữ liệu.',
        ],
        'Giới hạn trách nhiệm' => [
            'Dịch vụ được cung cấp theo hiện trạng. Trong phạm vi pháp luật cho phép, chúng tôi không chịu trách nhiệm cho thiệt hại phát sinh từ số liệu sai, thiếu hoặc từ việc dịch vụ bị gián đoạn.',
        ],
        'Thay đổi điều khoản' => [
            'Chúng tôi có thể cập nhật các điều khoản này. Thay đổi quan trọng sẽ được thông báo trong ứng dụng. Tiếp tục sử dụng nghĩa là bạn đồng ý với bản mới.',
        ],
    ];
@endphp

<x-privacy-layout title="Điều khoản sử dụng" :sections="$sections">
    <x-slot:intro>
        <p class="text-slate-600">Ngắn gọn, dễ hiểu: ứng dụng làm gì, bạn cần lưu ý gì. Cập nhật lần cuối: 13/11/2026.</p>
    </x-slot:intro>
</x-privacy-layout>
