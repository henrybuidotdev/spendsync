<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Default categories become shared system rows (user_id NULL). Each user's private copy of a default
 * (same name and type) is merged into the system row: transactions and rules are re-pointed, the copy deleted.
 */
return new class extends Migration
{
    private const SYSTEM_CATEGORIES = [
        ['Ăn uống', '🍜', 'expense'],
        ['Đi lại', '🛵', 'expense'],
        ['Mua sắm', '🛍️', 'expense'],
        ['Hóa đơn', '🧾', 'expense'],
        ['Nhà cửa', '🏠', 'expense'],
        ['Giải trí', '🎮', 'expense'],
        ['Sức khỏe', '💊', 'expense'],
        ['Giáo dục', '📚', 'expense'],
        ['Chuyển khoản', '🔁', 'expense'],
        ['Khác', '📦', 'expense'],
        ['Lương', '💰', 'income'],
        ['Thu khác', '➕', 'income'],
    ];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        DB::transaction(function () {
            foreach (self::SYSTEM_CATEGORIES as [$name, $icon, $type]) {
                $systemId = DB::table('categories')->insertGetId([
                    'user_id' => null,
                    'name' => $name,
                    'icon' => $icon,
                    'type' => $type,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $copies = DB::table('categories')
                    ->whereNotNull('user_id')
                    ->where('name', $name)
                    ->where('type', $type)
                    ->pluck('id');

                if ($copies->isEmpty()) {
                    continue;
                }

                DB::table('transactions')->whereIn('category_id', $copies)->update(['category_id' => $systemId]);
                DB::table('category_rules')->whereIn('category_id', $copies)->update(['category_id' => $systemId]);
                DB::table('categories')->whereIn('id', $copies)->delete();
            }
        });
    }

    /** Lossy: transactions in system categories become uncategorized and their rules are dropped. */
    public function down(): void
    {
        DB::table('categories')->whereNull('user_id')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
