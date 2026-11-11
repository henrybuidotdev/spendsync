<?php

namespace App\Categorization;

use App\Enums\MatchField;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\Transaction;
use App\Parsing\Support\TextNormalizer;
use Illuminate\Support\Collection;

/**
 * Counterparty rules match the whole normalized counterparty; description rules match a normalized substring.
 * Counterparty rules are checked first.
 */
class CategoryMatcher
{
    public function match(Transaction $transaction): ?CategoryRule
    {
        $rules = CategoryRule::where('user_id', $transaction->user_id)->get();

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

    /** Creates or updates the rule that maps this transaction's counterparty (or description) to the category. */
    public function remember(Transaction $transaction, Category $category): CategoryRule
    {
        $field = filled($transaction->counterparty) ? MatchField::Counterparty : MatchField::Description;
        $value = $field === MatchField::Counterparty ? $transaction->counterparty : $transaction->description;

        return CategoryRule::updateOrCreate(
            [
                'user_id' => $transaction->user_id,
                'match_field' => $field,
                'match_value' => TextNormalizer::fold($value),
            ],
            ['category_id' => $category->id],
        );
    }

    /**
     * Applies a rule to the user's existing uncategorized transactions. Returns how many were updated.
     * ponytail: filters in PHP because SQL can't strip Vietnamese accents; fine for one user's history.
     */
    public function backfill(CategoryRule $rule): int
    {
        $ids = Transaction::where('user_id', $rule->user_id)
            ->whereNull('category_id')
            ->get(['id', 'user_id', 'counterparty', 'description'])
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
        return $rules->first(fn (CategoryRule $rule) => $rule->match_field === MatchField::Counterparty && $this->matches($rule, $transaction))
            ?? $rules->first(fn (CategoryRule $rule) => $rule->match_field === MatchField::Description && $this->matches($rule, $transaction));
    }

    private function matches(CategoryRule $rule, Transaction $transaction): bool
    {
        if ($rule->match_value === '') {
            return false;
        }

        if ($rule->match_field === MatchField::Counterparty) {
            return TextNormalizer::fold($transaction->counterparty) === $rule->match_value;
        }

        return str_contains(TextNormalizer::fold($transaction->description), $rule->match_value);
    }
}
