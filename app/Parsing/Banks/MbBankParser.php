<?php

// SYNTHETIC — replace with real samples

namespace App\Parsing\Banks;

use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;

/** Handles both the HTML notification and the compact "TK ...|GD: ...|SD: ...|ND: ..." format. */
class MbBankParser extends AbstractBankParser
{
    public function bankCode(): string
    {
        return 'MB';
    }

    public function parse(ParsedEmailInput $input): ?ParsedTransaction
    {
        if ($this->isNonTransaction($input)) {
            return null;
        }

        $text = str_replace('|', "\n", $input->text);
        $amount = $this->field($text, 'Phát sinh|GD');

        if ($amount === null) {
            return null;
        }

        $time = $this->field($text, 'Thời gian')
            ?? preg_replace('/^.*?VN[DĐ]/ui', '', $amount);

        return $this->transaction(
            input: $input,
            amount: $amount,
            balance: $this->field($text, 'Số dư khả dụng|Số dư|SD'),
            counterparty: $this->field($text, 'Tên đối ứng|Tên TK đối ứng'),
            description: $this->field($text, 'Nội dung|ND'),
            reference: $this->field($text, 'Mã giao dịch|Số tham chiếu'),
            time: $time,
        );
    }
}
