<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/*
 * The shared system categories (user_id NULL), the single source of truth for defaults.
 * Names are English translation keys; add the Vietnamese name to lang/vi.json for each new line.
 * Safe to run again: it only creates categories that don't exist yet (matched by name and type)
 * and never changes existing rows, so add a line here and re-run the seeder.
 * When a category is created, a user's private category with the same name and type is merged into it.
 * Changing a line's icon or order does nothing to an existing row, and renaming creates a new category;
 * do those in a migration instead.
 */
class SystemCategorySeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string, 2: CategoryType, 3: int}> name, icon, type, order (higher shows first) */
    private const CATEGORIES = [
        ['Food & drink', '🍜', CategoryType::Expense, 120],
        ['Transport', '🛵', CategoryType::Expense, 110],
        ['Shopping', '🛍️', CategoryType::Expense, 100],
        ['Bills', '🧾', CategoryType::Expense, 90],
        ['Home', '🏠', CategoryType::Expense, 80],
        ['Entertainment', '🎮', CategoryType::Expense, 70],
        ['Health', '💊', CategoryType::Expense, 60],
        ['Education', '📚', CategoryType::Expense, 50],
        ['Transfers', '🔁', CategoryType::Expense, 40],
        ['Wedding', '💍', CategoryType::Expense, 30],
        ['Cash withdrawal', '🏧', CategoryType::Expense, 20],
        ['Investment', '📈', CategoryType::Expense, 10],
        ['Other', '📦', CategoryType::Expense, 0],
        ['Salary', '💰', CategoryType::Income, 30],
        ['Gifts', '🎁', CategoryType::Income, 20],
        ['Sales', '🏷️', CategoryType::Income, 10],
        ['Other income', '➕', CategoryType::Income, 0],
        ['Lending', '🤝', CategoryType::DebtLoan, 30],
        ['Debt collection', '💵', CategoryType::DebtLoan, 20],
        ['Borrowing', '🏦', CategoryType::DebtLoan, 10],
        ['Debt repayment', '💸', CategoryType::DebtLoan, 0],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::CATEGORIES as [$name, $icon, $type, $order]) {
                $system = Category::firstOrCreate(
                    ['user_id' => null, 'name' => $name, 'type' => $type],
                    ['icon' => $icon, 'order' => $order],
                );

                if (! $system->wasRecentlyCreated) {
                    continue;
                }

                $copies = Category::whereNotNull('user_id')
                    ->where('name', $name)
                    ->where('type', $type)
                    ->pluck('id');

                if ($copies->isEmpty()) {
                    continue;
                }

                Transaction::whereIn('category_id', $copies)->update(['category_id' => $system->id]);
                CategoryRule::whereIn('category_id', $copies)->update(['category_id' => $system->id]);
                Category::whereIn('id', $copies)->delete();
            }
        });
    }
}
