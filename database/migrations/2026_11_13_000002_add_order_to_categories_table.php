<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* Higher order shows first. Existing system categories get the same order as SystemCategorySeeder. */
return new class extends Migration
{
    private const SYSTEM_ORDER = [
        'Food & drink' => 120,
        'Transport' => 110,
        'Shopping' => 100,
        'Bills' => 90,
        'Home' => 80,
        'Entertainment' => 70,
        'Health' => 60,
        'Education' => 50,
        'Transfers' => 40,
        'Wedding' => 30,
        'Cash withdrawal' => 20,
        'Investment' => 10,
        'Other' => 0,
        'Salary' => 30,
        'Gifts' => 20,
        'Sales' => 10,
        'Other income' => 0,
        'Lending' => 30,
        'Debt collection' => 20,
        'Borrowing' => 10,
        'Debt repayment' => 0,
    ];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('type');
        });

        foreach (self::SYSTEM_ORDER as $name => $order) {
            DB::table('categories')->whereNull('user_id')->where('name', $name)->update(['order' => $order]);
        }
    }
};
