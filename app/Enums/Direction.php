<?php

namespace App\Enums;

enum Direction: string
{
    case In = 'in';
    case Out = 'out';

    /** Money out is categorized with expense categories, money in with income categories. */
    public function categoryType(): CategoryType
    {
        return $this === self::In ? CategoryType::Income : CategoryType::Expense;
    }
}
