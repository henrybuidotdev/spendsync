<?php

use App\Mail\Inbox\FetchedMessage;
use App\Models\RawEmail;
use App\Models\User;
use App\Parsing\ParserRegistry;
use App\Parsing\Support\BalanceRedactor;

/* The balance each fixture email contains, exactly as written in the email. */
dataset('balances', [
    'MB/01_transfer_in' => ['MB/01_transfer_in', '12,345,678'],
    'MB/02_compact_out' => ['MB/02_compact_out', '10,345,678'],
    'MB/03_shopee_base64' => ['MB/03_shopee_base64', '10.495.678'],
    'MB/04_compact_in_large' => ['MB/04_compact_in_large', '10.954.678'],
    'TCB/01_debit_grab' => ['TCB/01_debit_grab', '8,150,000'],
    'TCB/02_credit_transfer' => ['TCB/02_credit_transfer', '8,500,000'],
    'TCB/03_electric_bill' => ['TCB/03_electric_bill', '5.500.000'],
    'VCB/02_salary_in' => ['VCB/02_salary_in', '40,420,000'],
    'VCB/03_qr_payment_text' => ['VCB/03_qr_payment_text', '15,331,000'],
]);

test('stored bank emails never contain the balance but still parse the same', function (string $fixture, string $balance) {
    $path = base_path("tests/Fixtures/emails/{$fixture}.eml");
    $message = FetchedMessage::fromEml(file_get_contents($path));
    $expected = json_decode(file_get_contents(base_path("tests/Fixtures/emails/{$fixture}.expected.json")), true);

    expect($message->html.$message->text)->toContain($balance);

    $rawEmail = RawEmail::storeFetched(User::factory()->create()->id, null, $message)->fresh();

    expect((string) $rawEmail->body_html)->not->toContain($balance)
        ->and((string) $rawEmail->body_text)->not->toContain($balance)
        ->and($rawEmail->body_html.$rawEmail->body_text)->toContain(BalanceRedactor::MASK);

    $parser = app(ParserRegistry::class)->findFor($rawEmail->from_address, $rawEmail->subject);

    expect($parser->parse($rawEmail->toParserInput())?->toArray())->toBe($expected);
})->with('balances');

test('the redactor keeps the label and leaves other amounts alone', function () {
    expect(BalanceRedactor::redact('PS: -150,000VND|SD: 10,345,678VND|ND: QR'))->toBe('PS: -150,000VND|SD: ***|ND: QR')
        ->and(BalanceRedactor::redact('Số dư cuối: 15,331,000 VND'))->toBe('Số dư cuối: ***')
        ->and(BalanceRedactor::redact('<td>Số dư/Balance:</td><td><b>VND 8,500,000</b></td>'))->toBe('<td>Số dư/Balance:</td><td><b>***</b></td>')
        ->and(BalanceRedactor::redact(null))->toBeNull();
});
