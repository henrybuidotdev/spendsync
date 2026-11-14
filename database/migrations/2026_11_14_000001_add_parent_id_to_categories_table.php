<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* One level of nesting: a child category belongs to a top-level parent of the same type. Deleting a parent makes its children top-level. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained('categories')->nullOnDelete();
        });
    }
};
