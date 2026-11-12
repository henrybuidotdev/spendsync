<?php

namespace App\Parsing\Banks;

use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;
use App\Parsing\Support\TextNormalizer;

/**
 * Card alerts ("Thông báo giao dịch thẻ") come from a separate sender and use the same stacked layout.
 *
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

        if (str_contains($input->text, "Số tiền\nTransaction Amount\n")) {
            return $this->parseCardTransaction($input);
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

    /**
     * Card alerts ("Thông báo giao dịch thẻ"), often in a foreign currency with no VND figure.
     * A successful charge is money out; a cancelled one ("Huỷ") is a refund of that charge, so money back in.
     */
    private function parseCardTransaction(ParsedEmailInput $input): ?ParsedTransaction
    {
        $text = $input->text;
        $status = TextNormalizer::fold((string) $this->blockField($text, 'Tình trạng giao dịch', 'Status of Transaction'));

        $isRefund = str_starts_with($status, 'huy');

        if (! $isRefund && ! str_starts_with($status, 'thanh cong')) {
            return null;
        }

        return $this->transaction(
            input: $input,
            amount: $this->blockField($text, 'Số tiền', 'Transaction Amount'),
            directionHint: $isRefund ? 'ghi có' : 'ghi nợ',
            counterparty: $this->blockField($text, 'Sử dụng tại', 'At'),
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
            convertedAmount: $this->field($text, 'Số tiền quy đổi'),
        );
    }
}
