<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* Removes the Home system category. Its transactions and rules move to Other so nothing becomes uncategorized. */
return new class extends Migration
{
    public function up(): void
    {
        $homeId = DB::table('categories')->whereNull('user_id')->where('name', 'Home')->where('type', 'expense')->value('id');

        if ($homeId === null) {
            return;
        }

        $otherId = DB::table('categories')->whereNull('user_id')->where('name', 'Other')->where('type', 'expense')->value('id');

        DB::transaction(function () use ($homeId, $otherId) {
            DB::table('transactions')->where('category_id', $homeId)->update(['category_id' => $otherId]);
            DB::table('category_rules')->where('category_id', $homeId)->update(['category_id' => $otherId]);
            DB::table('categories')->where('parent_id', $homeId)->update(['parent_id' => null]);
            DB::table('categories')->where('id', $homeId)->delete();
        });
    }
};
