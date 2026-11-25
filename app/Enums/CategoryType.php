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
            self::Expense => __('Expense'),
            self::Income => __('Income'),
            self::DebtLoan => __('Debt/Loan'),
        };
    }
}
