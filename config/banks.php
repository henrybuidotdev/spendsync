<?php

use App\Enums\Direction;
use App\Parsing\Banks\CakeParser;
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

    'CAKE' => [
        'name' => 'CAKE by VPBank',
        'senders' => [
            'no-reply@cake.vn', // verified with real transfer alerts
        ],
        'parser' => CakeParser::class,
        'directions' => [Direction::In, Direction::Out],
    ],

    // 'VPB' => [
    //     'name' => 'VPBank',
    //     'senders' => [
    //         'vpbankonline@vpb.com.vn', // TODO verify
    //     ],
    //     'parser' => '',
    //     'directions' => [Direction::Out],
    // ],
];
