<?php

namespace App\Parsing\Banks;

use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;

/**
 * VPBank NEO "Transfer successful" confirmations, built from a real redacted sample: always money leaving the account.
 * The details are a two-column table, so a line holds two fields, e.g.
 * "Tài khoản ghi có: 316QR... Số tiền ghi có: 28,000 VND"; a left-column value ends where the right-column label starts.
 */
class VpBankParser extends AbstractBankParser
{
    private const RIGHT_COLUMN_LABELS = 'Ngày, giờ giao dịch|Số tiền trích nợ|Số tiền ghi có|Số tiền phí';

    public function bankCode(): string
    {
        return 'VPB';
    }

    public function parse(ParsedEmailInput $input): ?ParsedTransaction
    {
        if ($this->isNonTransaction($input)) {
            return null;
        }

        $text = $input->text;
        $amount = $this->cell($text, 'Số tiền trích nợ');

        if ($amount === null) {
            return null;
        }

        return $this->transaction(
            input: $input,
            amount: $amount,
            directionHint: 'ghi nợ',
            counterparty: $this->cell($text, 'Tên người hưởng'),
            counterpartyAccount: $this->cell($text, 'Tài khoản ghi có'),
            description: $this->cell($text, 'Nội dung chuyển tiền'),
            reference: $this->cell($text, 'Mã giao dịch'),
            time: $this->cell($text, 'Ngày, giờ giao dịch'),
        );
    }

    private function cell(string $text, string $label): ?string
    {
        $pattern = '/(?:^|\s)'.preg_quote($label, '/').':?[ \t]+(.+?)(?=\s+(?:'.self::RIGHT_COLUMN_LABELS.'):|[ \t]*$)/mu';

        if (! preg_match($pattern, $text, $match)) {
            return null;
        }

        return trim($match[1]);
    }
}
