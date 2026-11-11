<?php

use App\Parsing\Banks\MbBankParser;
use App\Parsing\Banks\TechcombankParser;
use App\Parsing\Banks\VietcombankParser;

/*
 * Supported banks: code => display name, sender addresses (lowercase) and parser class.
 * Only these senders are ever searched over IMAP.
 */
return [
    'VCB' => [
        'name' => 'Vietcombank',
        'senders' => [
            'vcbdigibank@info.vietcombank.com.vn', // TODO verify
        ],
        'parser' => VietcombankParser::class,
    ],

    'TCB' => [
        'name' => 'Techcombank',
        'senders' => [
            'alert@techcombank.com.vn', // TODO verify
        ],
        'parser' => TechcombankParser::class,
    ],

    'MB' => [
        'name' => 'MB Bank',
        'senders' => [
            'mbbank@mbbank.com.vn', // TODO verify
        ],
        'parser' => MbBankParser::class,
    ],
];
