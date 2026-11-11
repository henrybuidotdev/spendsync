<?php

// SYNTHETIC — replace with real samples

namespace App\Parsing\Banks;

use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;

/** Bilingual "Nhãn/Label: value" notifications; amounts are unsigned, direction comes from the transaction type. */
class TechcombankParser extends AbstractBankParser
{
    public function bankCode(): string
    {
        return 'TCB';
    }

    public function parse(ParsedEmailInput $input): ?ParsedTransaction
    {
        if ($this->isNonTransaction($input)) {
            return null;
        }

        $text = $input->text;
        $amount = $this->field($text, 'Số tiền(?:\s*\/\s*Amount)?');

        if ($amount === null) {
            return null;
        }

        return $this->transaction(
            input: $input,
            amount: $amount,
            directionHint: $this->field($text, 'Loại giao dịch(?:\s*\/\s*Transaction type)?'),
            balance: $this->field($text, 'Số dư(?:\s*\/\s*Balance)?'),
            counterparty: $this->field($text, '(?:Người nhận|Người chuyển)(?:\s*\/\s*(?:Beneficiary|Remitter))?'),
            description: $this->field($text, 'Nội dung(?:\s*\/\s*Description)?'),
            reference: $this->field($text, 'Mã giao dịch(?:\s*\/\s*Reference)?'),
            time: $this->field($text, 'Thời gian(?:\s*\/\s*Time)?'),
        );
    }
}
