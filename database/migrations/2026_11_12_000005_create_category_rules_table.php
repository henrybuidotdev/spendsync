<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('match_field', ['counterparty', 'description']);
            $table->string('match_value');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'match_field', 'match_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rules');
    }
};
