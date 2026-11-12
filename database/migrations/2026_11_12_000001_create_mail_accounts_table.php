<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('gmail');
            $table->string('email');
            $table->text('app_password')->nullable();
            $table->enum('status', ['active', 'needs_reconnect', 'disabled'])->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
