<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Adds the debt/loan category type with its shared defaults. A user's private copy with the same name is merged into the system row.
 */
return new class extends Migration
{
    private const SYSTEM_CATEGORIES = [
        ['Cho vay', '🤝'],
        ['Trả nợ', '💸'],
        ['Thu nợ', '💵'],
        ['Đi vay', '🏦'],
    ];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('type', ['expense', 'income', 'debt_loan'])->default('expense')->change();
        });

        DB::transaction(function () {
            foreach (self::SYSTEM_CATEGORIES as [$name, $icon]) {
                $systemId = DB::table('categories')->insertGetId([
                    'user_id' => null,
                    'name' => $name,
                    'icon' => $icon,
                    'type' => 'debt_loan',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $copies = DB::table('categories')
                    ->whereNotNull('user_id')
                    ->where('name', $name)
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
};
