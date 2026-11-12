<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_email_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('bank_code');
            $table->enum('direction', ['in', 'out']);
            $table->unsignedBigInteger('amount');
            $table->bigInteger('balance_after')->nullable();
            $table->string('counterparty')->nullable();
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
