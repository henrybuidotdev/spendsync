<?php

use App\Parsing\Support\DateParser;
use App\Parsing\Support\MoneyParser;
use App\Parsing\Support\TextNormalizer;

test('money parser handles separators, suffixes and signs', function (string $input, ?int $expected) {
    expect(MoneyParser::parse($input))->toBe($expected);
})->with([
    ['1,250,000 VND', 1250000],
    ['-1.250.000 VND', -1250000],
    ['+500,000đ', 500000],
    ['1.250.000,00', 1250000],
    ['1,250,000.00', 1250000],
    ['1.250.000,00 VND', 1250000],
    ['50.000₫', 50000],
    ['50.000 ₫', 50000],
    ['- 75,000 VNĐ', -75000],
    ['−200.000 VND', -200000],
    ['1250000', 1250000],
    ['+1 250 000 VND', 1250000],
    ['999', 999],
    ['1.000', 1000],
    ['12,500,000,000 VND', 12500000000],
    ['Số tiền: -35,000 VND.', -35000],
    ['1.234,56', 1235],
    ['VND 2,000,000', 2000000],
    ['abc', null],
    ['', null],
]);

test('date parser handles common bank formats in Asia/Ho_Chi_Minh', function (string $input, string $expected) {
    $date = DateParser::parse($input);

    expect($date->format('Y-m-d H:i:s'))->toBe($expected)
        ->and($date->getTimezone()->getName())->toBe('Asia/Ho_Chi_Minh');
})->with([
    ['12/11/2026 14:03:21', '2026-11-12 14:03:21'],
    ['12-11-2026 14:03', '2026-11-12 14:03:00'],
    ['14:03 12/11/2026', '2026-11-12 14:03:00'],
    ['14:03:21 12/11/2026', '2026-11-12 14:03:21'],
    ['Thời gian: 05/01/2026 08:00', '2026-01-05 08:00:00'],
    ['2026-11-12 14:03:21', '2026-11-12 14:03:21'],
    ['1/2/26 9:05', '2026-02-01 09:05:00'],
    ['12.11.2026 23:59:59', '2026-11-12 23:59:59'],
    ['12/11/2026', '2026-11-12 00:00:00'],
]);

test('date parser converts to UTC correctly', function () {
    expect(DateParser::parse('12/11/2026 14:03:21')->utc()->format('Y-m-d H:i:s'))->toBe('2026-11-12 07:03:21');
});

test('date parser rejects invalid dates', function (string $input) {
    expect(DateParser::parse($input))->toBeNull();
})->with(['31/02/2026 10:00', 'no date here', '12/13/2026', '12/11/2026 25:00']);

test('text normalizer converts html to clean lines', function () {
    $html = <<<'HTML'
        <html><head><style>td{color:red}</style></head><body>
        <table><tr><td>Số tiền:</td><td>&nbsp;-1,250,000&nbsp;VND</td></tr>
        <tr><td>Nội   dung:</td><td>CHUYEN&#32;TIEN</td></tr></table>
        <p>Cảm ơn<br>Quý khách</p><!-- hidden --></body></html>
        HTML;

    expect(TextNormalizer::htmlToText($html))->toBe("Số tiền: -1,250,000 VND\nNội dung: CHUYEN TIEN\nCảm ơn\nQuý khách");
});

test('text normalizer composes decomposed unicode to NFC', function () {
    $decomposed = "Nguye\u{0302}\u{0303}n"; // "Nguyễn" in NFD

    expect(TextNormalizer::clean($decomposed))->toBe('Nguyễn');
});

test('text normalizer folds Vietnamese diacritics for matching', function (string $input, string $expected) {
    expect(TextNormalizer::fold($input))->toBe($expected);
})->with([
    ['NGUYỄN VĂN ĐỨC', 'nguyen van duc'],
    ['Cà phê   Highlands', 'ca phe highlands'],
    ['Điện lực EVN', 'dien luc evn'],
    ["Nguye\u{0302}\u{0303}n", 'nguyen'],
    ['ơ ư ă â ê ô', 'o u a a e o'],
]);

test('money parser detects currency and returns minor units for foreign amounts', function (string $input, ?array $expected) {
    expect(MoneyParser::parseWithCurrency($input))->toBe($expected);
})->with([
    ['12.50 USD', ['amount' => 1250, 'currency' => 'USD']],
    ['USD 1,234.56', ['amount' => 123456, 'currency' => 'USD']],
    ['$9.99', ['amount' => 999, 'currency' => 'USD']],
    ['-20 USD', ['amount' => -2000, 'currency' => 'USD']],
    ['12.50 USD (325,000 VND)', ['amount' => 1250, 'currency' => 'USD']],
    ['15,00 EUR', ['amount' => 1500, 'currency' => 'EUR']],
    ['1.250.000 VND', ['amount' => 1250000, 'currency' => 'VND']],
    ['500.000đ', ['amount' => 500000, 'currency' => 'VND']],
    ['-35,000', ['amount' => -35000, 'currency' => 'VND']],
    ['abc', null],
]);
