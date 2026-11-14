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
 * A line with a parent name (5th item) becomes a child of that system category; list children after their parent.
 * Changing a line's icon, order or parent does nothing to an existing row, and renaming creates a new category;
 * do those in a migration instead.
 */
class SystemCategorySeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string, 2: CategoryType, 3: int, 4?: string}> name, icon, type, order (higher shows first), parent name */
    private const CATEGORIES = [
        ['Food & drink', '🍜', CategoryType::Expense, 120],
        ['Coffee', '☕', CategoryType::Expense, 30, 'Food & drink'],
        ['Restaurants', '🍽️', CategoryType::Expense, 20, 'Food & drink'],
        ['Delivery', '🥡', CategoryType::Expense, 10, 'Food & drink'],
        ['Transport', '🛵', CategoryType::Expense, 110],
        ['Flights', '✈️', CategoryType::Expense, 30, 'Transport'],
        ['Taxi', '🚕', CategoryType::Expense, 20, 'Transport'],
        ['Vehicle maintenance', '🔧', CategoryType::Expense, 10, 'Transport'],
        ['Shopping', '🛍️', CategoryType::Expense, 100],
        ['Houseware', '🧴', CategoryType::Expense, 20, 'Shopping'],
        ['Beauty', '💄', CategoryType::Expense, 10, 'Shopping'],
        ['Bills', '🧾', CategoryType::Expense, 90],
        ['Rentals', '🔑', CategoryType::Expense, 70, 'Bills'],
        ['Water bill', '🚿', CategoryType::Expense, 60, 'Bills'],
        ['Phone bill', '📱', CategoryType::Expense, 50, 'Bills'],
        ['Electricity bill', '⚡', CategoryType::Expense, 40, 'Bills'],
        ['Gas bill', '🔥', CategoryType::Expense, 30, 'Bills'],
        ['Internet bill', '🌐', CategoryType::Expense, 20, 'Bills'],
        ['TV & streaming', '📺', CategoryType::Expense, 10, 'Bills'],
        ['Entertainment', '🎮', CategoryType::Expense, 70],
        ['Movies', '🎬', CategoryType::Expense, 30, 'Entertainment'],
        ['Games', '🕹️', CategoryType::Expense, 20, 'Entertainment'],
        ['Music & concerts', '🎵', CategoryType::Expense, 10, 'Entertainment'],
        ['Health', '💊', CategoryType::Expense, 60],
        ['Medicine', '💉', CategoryType::Expense, 40, 'Health'],
        ['Doctor & hospital', '🏥', CategoryType::Expense, 30, 'Health'],
        ['Dental', '🦷', CategoryType::Expense, 20, 'Health'],
        ['Gym & fitness', '🏋️', CategoryType::Expense, 10, 'Health'],
        ['Education', '📚', CategoryType::Expense, 50],
        ['Transfers', '🔁', CategoryType::Expense, 40],
        ['Gifts & Donations', '💝', CategoryType::Expense, 35],
        ['Wedding', '💍', CategoryType::Expense, 30],
        ['Cash withdrawal', '🏧', CategoryType::Expense, 20],
        ['Investment', '📈', CategoryType::Expense, 10],
        ['Other', '📦', CategoryType::Expense, 0],
        ['Salary', '💰', CategoryType::Income, 30],
        ['Gifts', '🎁', CategoryType::Income, 20],
        ['Sales', '🏷️', CategoryType::Income, 10],
        ['Refund', '↩️', CategoryType::Income, 5],
        ['Other income', '🤑', CategoryType::Income, 0],
        ['Lending', '🤝', CategoryType::DebtLoan, 30],
        ['Debt collection', '💵', CategoryType::DebtLoan, 20],
        ['Borrowing', '🏦', CategoryType::DebtLoan, 10],
        ['Debt repayment', '💸', CategoryType::DebtLoan, 0],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::CATEGORIES as $line) {
                [$name, $icon, $type, $order] = $line;
                $parentName = $line[4] ?? null;

                $system = Category::firstOrCreate(
                    ['user_id' => null, 'name' => $name, 'type' => $type],
                    [
                        'icon' => $icon,
                        'order' => $order,
                        'parent_id' => $parentName === null ? null : Category::system()->where('name', $parentName)->where('type', $type)->value('id'),
                    ],
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
