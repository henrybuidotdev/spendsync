<?php

namespace App\Enums;

enum Direction: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return $this === self::In ? __('Money in') : __('Money out');
    }

    /** Money out is an expense, money in is income. */
    public function categoryType(): CategoryType
    {
        return $this === self::In ? CategoryType::Income : CategoryType::Expense;
    }

    /**
     * Category tabs for this direction. Which debt/loan categories fit is decided by Category::DEBT_LOAN_DIRECTIONS.
     *
     * @return array<int, CategoryType>
     */
    public function categoryTypes(): array
    {
        return [$this->categoryType(), CategoryType::DebtLoan];
    }
}
