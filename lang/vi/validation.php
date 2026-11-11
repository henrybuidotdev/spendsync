<?php

/*
 * Only the rules this app uses; anything missing falls back to lang/en/validation.php.
 */
return [
    'accepted' => ':Attribute cần được chấp nhận.',
    'confirmed' => ':Attribute nhập lại không khớp.',
    'current_password' => 'Mật khẩu không đúng.',
    'email' => ':Attribute không phải là email hợp lệ.',
    'enum' => ':Attribute không hợp lệ.',
    'exists' => ':Attribute không tồn tại.',
    'in' => ':Attribute không hợp lệ.',
    'lowercase' => ':Attribute phải viết thường.',
    'max' => [
        'string' => ':Attribute không được dài quá :max ký tự.',
        'numeric' => ':Attribute không được lớn hơn :max.',
    ],
    'min' => [
        'string' => ':Attribute phải có ít nhất :min ký tự.',
        'numeric' => ':Attribute phải từ :min trở lên.',
    ],
    'password' => [
        'letters' => ':Attribute phải có ít nhất một chữ cái.',
        'mixed' => ':Attribute phải có cả chữ hoa và chữ thường.',
        'numbers' => ':Attribute phải có ít nhất một chữ số.',
        'symbols' => ':Attribute phải có ít nhất một ký tự đặc biệt.',
        'uncompromised' => ':Attribute này đã bị lộ trong một vụ rò rỉ dữ liệu. Vui lòng chọn mật khẩu khác.',
    ],
    'regex' => ':Attribute không đúng định dạng.',
    'required' => 'Vui lòng nhập :attribute.',
    'string' => ':Attribute phải là chuỗi ký tự.',
    'unique' => ':Attribute đã được sử dụng.',

    'attributes' => [
        'name' => 'tên',
        'email' => 'email',
        'password' => 'mật khẩu',
        'current_password' => 'mật khẩu hiện tại',
        'note' => 'ghi chú',
        'form.email' => 'email',
        'form.password' => 'mật khẩu',
    ],
];
