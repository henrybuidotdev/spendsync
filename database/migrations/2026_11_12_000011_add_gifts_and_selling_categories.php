<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * New shared income defaults. A user's private copy with the same name is merged into the system row.
 */
return new class extends Migration
{
    private const SYSTEM_CATEGORIES = [
        ['Quà tặng', '🎁'],
        ['Bán hàng', '🏷️'],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::SYSTEM_CATEGORIES as [$name, $icon]) {
                $systemId = DB::table('categories')->insertGetId([
                    'user_id' => null,
                    'name' => $name,
                    'icon' => $icon,
                    'type' => 'income',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $copies = DB::table('categories')
                    ->whereNotNull('user_id')
                    ->where('name', $name)
                    ->where('type', 'income')
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
