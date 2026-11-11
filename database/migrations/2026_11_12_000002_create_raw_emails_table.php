<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_id');
            $table->string('from_address');
            $table->string('subject')->default('');
            $table->timestamp('received_at');
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('bank_code')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->text('parse_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'message_id']);
            $table->index('bank_code');
        });
    }
};
