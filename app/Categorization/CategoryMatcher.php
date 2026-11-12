<?php

namespace App\Categorization;

use App\Enums\MatchField;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Transaction;
use App\Parsing\Support\TextNormalizer;
use Illuminate\Support\Collection;

/**
 * Account rules match the counterparty's account number, counterparty rules the whole normalized name, and
 * description rules a normalized substring, checked in that order (most stable identifier first). A rule only applies when its category type fits the direction
 * (expense for money out, income for money in).
 */
class CategoryMatcher
{
    public function match(Transaction $transaction): ?CategoryRule
    {
        $rules = CategoryRule::with('category')->where('user_id', $transaction->user_id)->get();

        return $this->firstMatching($rules, $transaction);
    }

    public function apply(Transaction $transaction): bool
    {
        $rule = $this->match($transaction);

        if ($rule === null) {
            return false;
        }

        $transaction->update(['category_id' => $rule->category_id]);

        return true;
    }

    /** Creates or updates the rule mapping this transaction's account number (or name, or description) to the category. */
    public function remember(Transaction $transaction, Category $category): CategoryRule
    {
        $field = self::ruleField($transaction);
        $value = match ($field) {
            MatchField::Account => $transaction->counterparty_account,
            MatchField::Counterparty => $transaction->counterparty,
            MatchField::Description => $transaction->description,
        };

        return CategoryRule::updateOrCreate(
            [
                'user_id' => $transaction->user_id,
                'match_field' => $field,
                'match_value' => TextNormalizer::fold($value),
            ],
            ['category_id' => $category->id],
        );
    }

    public static function ruleField(Transaction $transaction): MatchField
    {
        if (ctype_digit((string) $transaction->counterparty_account)) {
            return MatchField::Account;
        }

        if (filled($transaction->counterparty)) {
            return MatchField::Counterparty;
        }

        return MatchField::Description;
    }

    /**
     * Applies a rule to the user's existing uncategorized transactions. Returns how many were updated.
     * ponytail: filters in PHP because SQL can't strip Vietnamese accents; fine for one user's history.
     */
    public function backfill(CategoryRule $rule): int
    {
        $ids = Transaction::where('user_id', $rule->user_id)
            ->whereNull('category_id')
            ->get(['id', 'user_id', 'direction', 'counterparty', 'counterparty_account', 'description'])
            ->filter(fn (Transaction $transaction) => $this->matches($rule, $transaction))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return Transaction::whereIn('id', $ids)->update(['category_id' => $rule->category_id]);
    }

    /** @param Collection<int, CategoryRule> $rules */
    private function firstMatching(Collection $rules, Transaction $transaction): ?CategoryRule
    {
        foreach (MatchField::cases() as $field) {
            $rule = $rules->first(fn (CategoryRule $rule) => $rule->match_field === $field && $this->matches($rule, $transaction));

            if ($rule !== null) {
                return $rule;
            }
        }

        return null;
    }

    private function matches(CategoryRule $rule, Transaction $transaction): bool
    {
        if ($rule->match_value === '') {
            return false;
        }

        if (! in_array($rule->category->type, $transaction->direction->categoryTypes(), true)) {
            return false;
        }

        if ($rule->match_field === MatchField::Account) {
            return TextNormalizer::fold($transaction->counterparty_account) === $rule->match_value;
        }

        if ($rule->match_field === MatchField::Counterparty) {
            return TextNormalizer::fold($transaction->counterparty) === $rule->match_value;
        }

        return str_contains(TextNormalizer::fold($transaction->description), $rule->match_value);
    }
}
