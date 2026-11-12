<?php

namespace App\Enums;

enum CategoryType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case DebtLoan = 'debt_loan';

    public function label(): string
    {
        return match ($this) {
            self::Expense => __('Khoản chi'),
            self::Income => __('Khoản thu'),
            self::DebtLoan => __('Vay nợ'),
        };
    }
}
