<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('counterparty_account')->nullable()->after('counterparty');
        });

        Schema::table('category_rules', function (Blueprint $table) {
            $table->enum('match_field', ['account', 'counterparty', 'description'])->change();
        });
    }
};
