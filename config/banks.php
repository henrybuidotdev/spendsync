<?php

use App\Enums\Direction;
use App\Parsing\Banks\MbBankParser;
use App\Parsing\Banks\TechcombankParser;
use App\Parsing\Banks\VietcombankParser;

/*
 * Supported banks: code => display name, sender addresses (lowercase), parser class
 * and which directions the bank emails about (money in, money out or both).
 * Only these senders are ever searched over IMAP.
 */
return [
    'VCB' => [
        'name' => 'Vietcombank',
        'senders' => [
            'vcbdigibank@info.vietcombank.com.vn', // verified with real receipts
            'info@info.vietcombank.com.vn', // verified with a real card alert
        ],
        'parser' => VietcombankParser::class,
        'directions' => [Direction::Out],
    ],

    'TCB' => [
        'name' => 'Techcombank',
        'senders' => [
            'alert@techcombank.com.vn', // TODO verify
        ],
        'parser' => TechcombankParser::class,
        'directions' => [Direction::In, Direction::Out],
    ],

    'MB' => [
        'name' => 'MB Bank',
        'senders' => [
            'mbbank@mbbank.com.vn', // TODO verify
        ],
        'parser' => MbBankParser::class,
        'directions' => [Direction::In, Direction::Out],
    ],
];
