<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->after('amount');
            $table->unsignedBigInteger('original_amount')->nullable()->after('currency');
            $table->boolean('amount_estimated')->default(false)->after('original_amount');
        });
    }
};
