<?php

namespace App\Parsing\Banks;

use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;

/**
 * "[CAKE] Thông báo giao dịch thành công", built from real redacted samples:
 * each label on its own line with the value on the next, the amount signed ("+2.000 đ" in, "-2.000 đ" out).
 * The other party is the sender ("Tài khoản chuyển", "Tên người chuyển") for money in
 * and the receiver ("Tài khoản nhận", "Tên người nhận") for money out.
 */
class CakeParser extends AbstractBankParser
{
    public function bankCode(): string
    {
        return 'CAKE';
    }

    public function parse(ParsedEmailInput $input): ?ParsedTransaction
    {
        if ($this->isNonTransaction($input)) {
            return null;
        }

        $text = $input->text;
        $amount = $this->nextLine($text, 'Số tiền');

        if ($amount === null) {
            return null;
        }

        $party = str_starts_with($amount, '+') ? 'chuyển' : 'nhận';

        return $this->transaction(
            input: $input,
            amount: $amount,
            counterparty: $this->nextLine($text, "Tên người {$party}"),
            counterpartyAccount: $this->nextLine($text, "Tài khoản {$party}"),
            description: $this->nextLine($text, 'Nội dung giao dịch'),
            reference: $this->nextLine($text, 'Mã giao dịch'),
            time: $this->nextLine($text, 'Ngày giờ giao dịch'),
        );
    }

    private function nextLine(string $text, string $label): ?string
    {
        if (! preg_match('/^'.preg_quote($label, '/').'\n(.+)$/mu', $text, $match)) {
            return null;
        }

        return trim($match[1]);
    }
}
