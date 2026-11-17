<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* last_synced_at is how far the sync has got through the mailbox; last_run_at is when a sync last ran. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->timestamp('last_run_at')->nullable()->after('last_synced_at');
        });

        DB::table('mail_accounts')->update(['last_run_at' => DB::raw('last_synced_at')]);
    }
};
