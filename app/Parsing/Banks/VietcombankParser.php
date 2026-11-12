<?php

namespace App\Parsing\Banks;

use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;

/**
 * Receipts ("Biên lai chuyển tiền qua tài khoản", "Biên lai thanh toán") are built from real redacted samples:
 * stacked "Vietnamese label / English label / value" lines, always money leaving the user's account.
 *
 * SYNTHETIC — the "Label: value" balance-change format below is a guess; replace with real samples.
 */
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

        if (str_contains($input->text, "Số tiền\nAmount\n")) {
            return $this->parseReceipt($input);
        }

        return $this->parseBalanceNotification($input);
    }

    private function parseReceipt(ParsedEmailInput $input): ParsedTransaction
    {
        $text = $input->text;

        return $this->transaction(
            input: $input,
            amount: $this->blockField($text, 'Số tiền', 'Amount'),
            directionHint: 'ghi nợ',
            counterparty: $this->blockField($text, 'Tên người hưởng', 'Beneficiary Name'),
            counterpartyAccount: $this->blockField($text, 'Tài khoản người hưởng|Tài khoản hưởng', 'Credit Account'),
            description: $this->blockField($text, 'Nội dung chuyển tiền|Nội dung thanh toán', 'Details of Payment'),
            reference: $this->blockField($text, 'Số lệnh giao dịch', 'Order Number'),
            time: $this->blockField($text, 'Ngày, giờ giao dịch', 'Trans. Date, Time'),
        );
    }

    private function parseBalanceNotification(ParsedEmailInput $input): ?ParsedTransaction
    {
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
