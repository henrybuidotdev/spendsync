<?php

namespace App\Enums;

enum Direction: string
{
    case In = 'in';
    case Out = 'out';

    /**
     * Money out is categorized with expense categories, money in with income categories; debt/loan fits either.
     *
     * @return array<int, CategoryType>
     */
    public function categoryTypes(): array
    {
        return $this === self::In ?
            [CategoryType::Income, CategoryType::DebtLoan] :
            [CategoryType::Expense, CategoryType::DebtLoan];
    }
}
