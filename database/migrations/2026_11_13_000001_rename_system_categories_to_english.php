<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * System categories are stored under English names and translated for display (lang/*.json).
 * Users' own categories keep the name they typed.
 */
return new class extends Migration
{
    private const NAMES = [
        'Ăn uống' => 'Food & drink',
        'Đi lại' => 'Transport',
        'Mua sắm' => 'Shopping',
        'Hóa đơn' => 'Bills',
        'Nhà cửa' => 'Home',
        'Giải trí' => 'Entertainment',
        'Sức khỏe' => 'Health',
        'Giáo dục' => 'Education',
        'Chuyển khoản' => 'Transfers',
        'Cưới hỏi' => 'Wedding',
        'Rút tiền' => 'Cash withdrawal',
        'Khác' => 'Other',
        'Lương' => 'Salary',
        'Quà tặng' => 'Gifts',
        'Bán hàng' => 'Sales',
        'Thu khác' => 'Other income',
        'Cho vay' => 'Lending',
        'Trả nợ' => 'Debt repayment',
        'Thu nợ' => 'Debt collection',
        'Đi vay' => 'Borrowing',
    ];

    public function up(): void
    {
        foreach (self::NAMES as $vietnamese => $english) {
            DB::table('categories')
                ->whereNull('user_id')
                ->where('name', $vietnamese)
                ->update(['name' => $english]);
        }
    }
};
