<?php

use App\Mail\Inbox\FetchedMessage;
use App\Parsing\Banks\VietcombankParser;
use App\Parsing\ParsedEmailInput;
use App\Parsing\ParserRegistry;
use App\Parsing\UnparseableEmailException;
use Carbon\CarbonImmutable;

dataset('fixtures', function () {
    foreach (glob(__DIR__.'/../Fixtures/emails/*/*.eml') as $path) {
        yield basename(dirname($path)).'/'.basename($path) => [$path];
    }
});

test('every fixture parses to its expected transaction', function (string $path) {
    $message = FetchedMessage::fromEml(file_get_contents($path));
    $expected = json_decode(file_get_contents(preg_replace('/\.eml$/', '.expected.json', $path)), true);

    $parser = app(ParserRegistry::class)->findFor($message->from, $message->subject);

    expect($parser)->not->toBeNull()
        ->and($parser->bankCode())->toBe(basename(dirname($path)));

    $result = $parser->parse(ParsedEmailInput::make($message->from, $message->subject, $message->html, $message->text, $message->receivedAt));

    expect($result?->toArray())->toBe($expected);
})->with('fixtures');

test('each bank has at least three transaction fixtures and one non-transaction fixture', function () {
    foreach (array_keys(config('banks')) as $code) {
        $expectations = array_map(
            fn (string $file) => json_decode(file_get_contents($file), true),
            glob(base_path("tests/Fixtures/emails/{$code}/*.expected.json")),
        );

        expect(count(array_filter($expectations)))->toBeGreaterThanOrEqual(3, $code)
            ->and(in_array(null, $expectations, true))->toBeTrue($code);
    }
});

test('registry exposes all configured senders and matches display-name senders', function () {
    $registry = app(ParserRegistry::class);

    expect($registry->senders())->toContain('vcbdigibank@info.vietcombank.com.vn', 'no-reply@cake.vn')
        ->and($registry->findFor('Vietcombank <VCBDigibank@info.vietcombank.com.vn>', 'x')?->bankCode())->toBe('VCB')
        ->and($registry->findFor('scam@vietcombank.com.vn.evil.io', 'x'))->toBeNull();
});

function input(string $text, string $subject = 'Thông báo'): ParsedEmailInput
{
    return ParsedEmailInput::make('x@bank.vn', $subject, null, $text, CarbonImmutable::parse('2026-11-12 03:00:00', 'UTC'));
}

test('a transaction email with a redacted amount throws amount_missing', function () {
    (new VietcombankParser)->parse(input("Số tiền: *** VND\nNội dung: ABC"));
})->throws(UnparseableEmailException::class, 'amount_missing');

test('a transaction email without sign or type throws direction_unknown', function () {
    (new VietcombankParser)->parse(input("Số tiền: 100,000 VND\nNội dung: X"));
})->throws(UnparseableEmailException::class, 'direction_unknown');

test('direction comes from keywords when the amount has no sign', function (string $type, string $direction) {
    $result = (new VietcombankParser)->parse(input("Loại giao dịch: {$type}\nSố tiền: 100.000đ"));

    expect($result->direction->value)->toBe($direction);
})->with([
    ['Ghi nợ', 'out'],
    ['GHI CÓ', 'in'],
    ['Debit', 'out'],
    ['Credit', 'in'],
    ['Nhận tiền', 'in'],
    ['Trừ tiền', 'out'],
]);

test('falls back to received_at in local time when the body has no time', function () {
    $result = (new VietcombankParser)->parse(input('Số tiền: -10,000 VND'));

    expect($result->occurredAt->toIso8601String())->toBe('2026-11-12T10:00:00+07:00');
});

test('bank emails without an amount field are not transactions', function () {
    expect((new VietcombankParser)->parse(input('Sao kê tài khoản tháng 10 đã sẵn sàng.', 'Sao kê')))->toBeNull()
        ->and((new VietcombankParser)->parse(input('Ưu đãi hoàn tiền. Số tiền: +50,000 VND', 'Khuyến mãi')))->toBeNull();
});

test('amount label without colon inside html table cells is parsed', function () {
    $input = ParsedEmailInput::make('x', 's', '<table><tr><td>Số tiền</td><td>-12.000 VND</td></tr></table>', null, CarbonImmutable::now());

    expect((new VietcombankParser)->parse($input)->amount)->toBe(12000);
});

test('vietcombank receipts read the amount block, not the fee block', function () {
    $text = "Biên lai chuyển tiền qua tài khoản\nSố tiền\nAmount\n480,000 VND\nSố tiền phí\nCharge Amount\nNet income\nVAT\n0 VND";

    $result = (new VietcombankParser)->parse(input($text));

    expect($result->amount)->toBe(480000)
        ->and($result->direction->value)->toBe('out');
});

test('vietcombank receipts with a blank amount throw amount_missing', function () {
    (new VietcombankParser)->parse(input("Số tiền\nAmount\n*** VND\nSố tiền phí\nCharge Amount\n0 VND"));
})->throws(UnparseableEmailException::class, 'amount_missing');

test('receipt credit accounts keep letters and digits, and anything with other characters is dropped', function (string $account, ?string $expected) {
    $text = "Biên lai thanh toán\nTài khoản hưởng\nCredit Account\n{$account}\nSố tiền\nAmount\n100,000 VND";

    $result = app(VietcombankParser::class)
        ->parse(ParsedEmailInput::make('VCB <info@vietcombank.com.vn>', 'Biên lai', null, $text, CarbonImmutable::parse('2026-11-12')));

    expect($result->counterpartyAccount)->toBe($expected);
})->with([
    ['0451000555666', '0451000555666'],
    ['VQRQA1B2C3 ', 'VQRQA1B2C3'],
    ['abc123', 'ABC123'],
    ['MBVCB.123', null],
    ['GRAB*123', null],
]);

test('a foreign amount uses the VND the bank charged when the email states it', function () {
    $result = (new VietcombankParser)->parse(input("Số tiền: -12.50 USD\nSố tiền quy đổi: 330,000 VND\nNội dung: NETFLIX.COM"));

    expect($result->amount)->toBe(330000)
        ->and($result->currency)->toBe('USD')
        ->and($result->originalAmount)->toBe(1250)
        ->and($result->amountEstimated)->toBeFalse();
});

test('a foreign amount without a stated VND figure is estimated at the configured rate', function () {
    config(['services.fx.usd_vnd' => 26000]);

    $result = (new VietcombankParser)->parse(input("Số tiền: -12.50 USD\nNội dung: NETFLIX.COM"));

    expect($result->amount)->toBe(325000)
        ->and($result->direction->value)->toBe('out')
        ->and($result->amountEstimated)->toBeTrue();
});

test('a foreign currency with no rate and no stated VND figure is rejected, never saved as VND', function () {
    (new VietcombankParser)->parse(input("Số tiền: -15.00 EUR\nNội dung: SPOTIFY"));
})->throws(UnparseableEmailException::class, 'currency_unsupported');

test('a card alert that did not succeed is not a transaction', function () {
    $text = "Số tiền\nTransaction Amount\n22.22 USD\nSử dụng tại\nAt\nSHOP\nTình trạng giao dịch\nStatus of Transaction\nKhông thành công";

    expect((new VietcombankParser)->parse(input($text)))->toBeNull();
});
