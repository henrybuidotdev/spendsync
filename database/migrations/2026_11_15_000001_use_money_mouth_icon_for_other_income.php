<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->whereNull('user_id')
            ->where('name', 'Other income')
            ->where('icon', '➕')
            ->update(['icon' => '🤑']);
    }
};
