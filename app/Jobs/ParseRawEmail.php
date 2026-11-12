<?php

namespace App\Jobs;

use App\Categorization\CategoryMatcher;
use App\Models\RawEmail;
use App\Models\Transaction;
use App\Parsing\ParsedTransaction;
use App\Parsing\ParserRegistry;
use App\Parsing\UnparseableEmailException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ParseRawEmail implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public RawEmail $rawEmail) {}

    public function handle(ParserRegistry $registry, CategoryMatcher $matcher): void
    {
        $rawEmail = $this->rawEmail;
        $parser = $registry->findFor($rawEmail->from_address, $rawEmail->subject);

        if ($parser === null) {
            $rawEmail->update(['parse_error' => 'unsupported_sender', 'parsed_at' => null]);

            return;
        }

        $rawEmail->bank_code = $parser->bankCode();

        try {
            $result = $parser->parse($rawEmail->toParserInput());
        } catch (Throwable $exception) {
            $this->recordFailure($exception);

            return;
        }

        $rawEmail->fill(['parsed_at' => now(), 'parse_error' => null])->save();

        if ($result === null) {
            return;
        }

        $transaction = $this->storeTransaction($result);

        if ($transaction->category_id === null) {
            $matcher->apply($transaction);
        }
    }

    private function storeTransaction(ParsedTransaction $result): Transaction
    {
        return Transaction::updateOrCreate(
            ['raw_email_id' => $this->rawEmail->id],
            [
                'user_id' => $this->rawEmail->user_id,
                'bank_code' => $this->rawEmail->bank_code,
                'direction' => $result->direction,
                'amount' => $result->amount,
                'balance_after' => $result->balanceAfter,
                'counterparty' => $result->counterparty,
                'counterparty_account' => $result->counterpartyAccount,
                'description' => $result->description,
                'reference' => $result->reference,
                'occurred_at' => $result->occurredAt->utc(),
            ],
        );
    }

    /** Stores a short reason and logs ids only — never the email body. */
    private function recordFailure(Throwable $exception): void
    {
        $reason = $exception instanceof UnparseableEmailException ?
            $exception->getMessage() :
            'parser_error: '.class_basename($exception);

        $this->rawEmail->fill(['parse_error' => $reason, 'parsed_at' => null])->save();

        Log::warning('Bank email could not be parsed', [
            'raw_email_id' => $this->rawEmail->id,
            'bank_code' => $this->rawEmail->bank_code,
            'reason' => $reason,
        ]);
    }
}
