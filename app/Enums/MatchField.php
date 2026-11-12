<?php

namespace App\Enums;

enum MatchField: string
{
    case Account = 'account';
    case Counterparty = 'counterparty';
    case Description = 'description';
}
