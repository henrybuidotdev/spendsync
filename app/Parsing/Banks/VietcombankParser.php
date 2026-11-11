<?php

// SYNTHETIC — replace with real samples

namespace App\Parsing\Banks;

use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;

class VietcombankParser extends AbstractBankParser
{
    public function bankCode(): string
    {
        return 'VCB';
    }

    public function parse(ParsedEmailInput $input): ?ParsedTransaction
    {
        if ($this->isNonTransaction($input)) {
            return null;
        }

        $text = $input->text;
        $amount = $this->field($text, 'Số tiền giao dịch|Số tiền');

        if ($amount === null) {
            return null;
        }

        return $this->transaction(
            input: $input,
            amount: $amount,
            directionHint: $this->field($text, 'Loại giao dịch'),
            balance: $this->field($text, 'Số dư cuối|Số dư sau giao dịch|Số dư'),
            counterparty: $this->field($text, 'Tên người hưởng|Người hưởng|Tên người chuyển|Người chuyển'),
            description: $this->field($text, 'Nội dung chuyển tiền|Nội dung'),
            reference: $this->field($text, 'Số tham chiếu|Mã giao dịch'),
            time: $this->field($text, 'Thời gian giao dịch|Thời gian|Ngày giao dịch'),
        );
    }
}
