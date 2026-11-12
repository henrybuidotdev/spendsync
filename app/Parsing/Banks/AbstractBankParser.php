<?php

namespace App\Parsing\Banks;

use App\Enums\Direction;
use App\Parsing\BankEmailParser;
use App\Parsing\ParsedEmailInput;
use App\Parsing\ParsedTransaction;
use App\Parsing\ParserRegistry;
use App\Parsing\Support\DateParser;
use App\Parsing\Support\ExchangeRates;
use App\Parsing\Support\MoneyParser;
use App\Parsing\Support\TextNormalizer;
use App\Parsing\UnparseableEmailException;

abstract class AbstractBankParser implements BankEmailParser
{
    public function senders(): array
    {
        return array_map('strtolower', config("banks.{$this->bankCode()}.senders", []));
    }

    public function supports(string $from, string $subject): bool
    {
        return in_array(ParserRegistry::address($from), $this->senders(), true);
    }

    /** First line value after any of the given label regex fragments, e.g. "Số tiền: -50,000 VND". */
    protected function field(string $text, string $labels): ?string
    {
        if (! preg_match('/^\s*(?:'.$labels.')\s*:?\s*(.+?)\s*$/mui', $text, $match)) {
            return null;
        }

        return $match[1];
    }

    /**
     * Value on the line after a "Vietnamese label / English label" pair, as in receipts laid out as
     * "Số tiền\nAmount\n480,000 VND". Each argument may list alternatives separated by "|".
     */
    protected function blockField(string $text, string $labels, string $englishLabels): ?string
    {
        $quote = fn (string $alternatives) => implode('|', array_map(fn (string $label) => preg_quote($label, '/'), explode('|', $alternatives)));

        if (! preg_match('/^(?:'.$quote($labels).')\n(?:'.$quote($englishLabels).')\n(.+)$/mu', $text, $match)) {
            return null;
        }

        return trim($match[1]);
    }

    protected function isNonTransaction(ParsedEmailInput $input): bool
    {
        return (bool) preg_match('/\b(otp|ma xac thuc|khuyen mai|uu dai)\b/', TextNormalizer::fold($input->subject.' '.$input->text));
    }

    protected function transaction(
        ParsedEmailInput $input,
        ?string $amount,
        ?string $directionHint = null,
        ?string $counterparty = null,
        ?string $counterpartyAccount = null,
        ?string $description = null,
        ?string $reference = null,
        ?string $time = null,
        ?string $convertedAmount = null,
    ): ParsedTransaction {
        $money = $amount === null ? null : MoneyParser::parseWithCurrency($amount);

        if (! $money || ! $money['amount']) {
            throw new UnparseableEmailException('amount_missing');
        }

        $signedAmount = $money['amount'];
        $foreign = null;

        if ($money['currency'] !== 'VND') {
            $foreign = $this->toVnd($money['currency'], abs($signedAmount), $convertedAmount);
            $signedAmount = $foreign['vnd'];
        }

        $direction = $this->directionFromSign($amount) ?? $this->directionFromKeywords((string) $directionHint);

        if ($direction === null) {
            throw new UnparseableEmailException('direction_unknown');
        }

        $occurredAt = ($time ? DateParser::parse($time) : null)
            ?? $input->receivedAt->setTimezone(config('app.display_timezone'));

        return new ParsedTransaction(
            direction: $direction,
            amount: abs($signedAmount),
            counterparty: $this->clean($counterparty),
            counterpartyAccount: $this->accountNumber($counterpartyAccount),
            description: $this->clean($description),
            reference: $this->clean($reference),
            occurredAt: $occurredAt,
            currency: $foreign ? $money['currency'] : null,
            originalAmount: $foreign ? abs($money['amount']) : null,
            amountEstimated: $foreign['estimated'] ?? false,
        );
    }

    /**
     * VND the bank charged when the email states it, otherwise an estimate at the configured rate.
     *
     * @return array{
     *     vnd: int,
     *     estimated: bool
     * }
     */
    private function toVnd(string $currency, int $minorAmount, ?string $convertedAmount): array
    {
        $charged = $convertedAmount === null ? null : MoneyParser::parse($convertedAmount);

        if ($charged) {
            return ['vnd' => abs($charged), 'estimated' => false];
        }

        $rate = ExchangeRates::vndPer($currency);

        if (! $rate) {
            throw new UnparseableEmailException('currency_unsupported');
        }

        return ['vnd' => (int) round($minorAmount * $rate / 100), 'estimated' => true];
    }

    protected function directionFromSign(string $amount): ?Direction
    {
        if (! preg_match('/(?:^|[\s:])([+\-−])\s*\d/u', $amount, $match)) {
            return null;
        }

        return $match[1] === '+' ? Direction::In : Direction::Out;
    }

    protected function directionFromKeywords(string $hint): ?Direction
    {
        $hint = TextNormalizer::fold($hint);

        if (preg_match('/\b(ghi no|debit|tru)\b/', $hint)) {
            return Direction::Out;
        }

        if (preg_match('/\b(ghi co|credit|nhan)\b/', $hint)) {
            return Direction::In;
        }

        return null;
    }

    /** Account numbers are letters and digits only; anything with other characters (".", "*", …) isn't one. */
    private function accountNumber(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', '', (string) $value);

        return preg_match('/^[A-Za-z0-9]+$/', $value) ? strtoupper($value) : null;
    }

    private function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
