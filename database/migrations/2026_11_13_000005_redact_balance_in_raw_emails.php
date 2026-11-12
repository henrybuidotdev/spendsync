<?php

use App\Parsing\Support\BalanceRedactor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* Masks the account balance in emails stored before BalanceRedactor existed. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('raw_emails')->select(['id', 'body_html', 'body_text'])->chunkById(200, function ($emails) {
            foreach ($emails as $email) {
                $html = BalanceRedactor::redact($email->body_html);
                $text = BalanceRedactor::redact($email->body_text);

                if ($html === $email->body_html && $text === $email->body_text) {
                    continue;
                }

                DB::table('raw_emails')->where('id', $email->id)->update(['body_html' => $html, 'body_text' => $text]);
            }
        });
    }
};
