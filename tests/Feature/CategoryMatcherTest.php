<?php

use App\Categorization\CategoryMatcher;
use App\Enums\MatchField;
use App\Models\CategoryRule;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->food = $this->user->availableCategories()->where('name', 'Ăn uống')->first();
    $this->bills = $this->user->availableCategories()->where('name', 'Hóa đơn')->first();
    $this->matcher = app(CategoryMatcher::class);
});

function rule(User $user, MatchField $field, string $value, int $categoryId): CategoryRule
{
    return CategoryRule::create(['user_id' => $user->id, 'match_field' => $field, 'match_value' => $value, 'category_id' => $categoryId]);
}

test('counterparty rules match ignoring case and accents', function () {
    rule($this->user, MatchField::Counterparty, 'Bún Bò Huế Cô Ba', $this->food->id);
    $transaction = Transaction::factory()->for($this->user)->create(['counterparty' => 'BUN BO HUE CO BA']);

    expect($this->matcher->match($transaction)?->category_id)->toBe($this->food->id);
});

test('counterparty rules require the whole name, description rules match a substring', function () {
    rule($this->user, MatchField::Counterparty, 'EVN', $this->bills->id);
    rule($this->user, MatchField::Description, 'tiền điện', $this->bills->id);

    $partialName = Transaction::factory()->for($this->user)->create(['counterparty' => 'EVN HCMC', 'description' => 'abc']);
    $description = Transaction::factory()->for($this->user)->create(['counterparty' => null, 'description' => 'Thanh toan TIEN DIEN T10']);

    expect($this->matcher->match($partialName))->toBeNull()
        ->and($this->matcher->match($description)?->category_id)->toBe($this->bills->id);
});

test('counterparty rules win over description rules', function () {
    rule($this->user, MatchField::Description, 'grab', $this->bills->id);
    rule($this->user, MatchField::Counterparty, 'grab vietnam', $this->food->id);
    $transaction = Transaction::factory()->for($this->user)->create(['counterparty' => 'Grab Vietnam', 'description' => 'GRAB FOOD']);

    expect($this->matcher->match($transaction)->category_id)->toBe($this->food->id);
});

test('other users rules never apply', function () {
    $other = User::factory()->create();
    rule($other, MatchField::Counterparty, 'shopee', $other->availableCategories()->first()->id);
    $transaction = Transaction::factory()->for($this->user)->create(['counterparty' => 'Shopee']);

    expect($this->matcher->apply($transaction))->toBeFalse()
        ->and($transaction->fresh()->category_id)->toBeNull();
});

test('remember creates or updates one rule per counterparty', function () {
    $transaction = Transaction::factory()->for($this->user)->create(['counterparty' => 'Trần Thị Bích']);

    $this->matcher->remember($transaction, $this->food);
    $rule = $this->matcher->remember($transaction, $this->bills);

    expect(CategoryRule::count())->toBe(1)
        ->and($rule->match_field)->toBe(MatchField::Counterparty)
        ->and($rule->match_value)->toBe('tran thi bich')
        ->and($rule->category_id)->toBe($this->bills->id);
});

test('remember falls back to a description rule when there is no counterparty', function () {
    $transaction = Transaction::factory()->for($this->user)->create(['counterparty' => null, 'description' => 'THANH TOAN QR BUN BO']);

    $rule = $this->matcher->remember($transaction, $this->food);

    expect($rule->match_field)->toBe(MatchField::Description)
        ->and($rule->match_value)->toBe('thanh toan qr bun bo');
});

test('backfill only touches the owners uncategorized matching transactions', function () {
    $rule = rule($this->user, MatchField::Counterparty, 'Trần Thị Bích', $this->food->id);
    $match = Transaction::factory()->for($this->user)->create(['counterparty' => 'TRAN THI BICH']);
    $alreadySet = Transaction::factory()->for($this->user)->create(['counterparty' => 'TRAN THI BICH', 'category_id' => $this->bills->id]);
    $different = Transaction::factory()->for($this->user)->create(['counterparty' => 'LE VAN A']);
    $otherUser = Transaction::factory()->create(['counterparty' => 'TRAN THI BICH']);

    expect($this->matcher->backfill($rule))->toBe(1)
        ->and($match->fresh()->category_id)->toBe($this->food->id)
        ->and($alreadySet->fresh()->category_id)->toBe($this->bills->id)
        ->and($different->fresh()->category_id)->toBeNull()
        ->and($otherUser->fresh()->category_id)->toBeNull();
});

test('rules never assign a category of the wrong kind', function () {
    $rule = rule($this->user, MatchField::Description, 'grab', $this->food->id);
    $payment = Transaction::factory()->for($this->user)->create(['description' => 'GRAB FOOD']);
    $refund = Transaction::factory()->for($this->user)->income()->create(['description' => 'GRAB HOAN TIEN']);

    expect($this->matcher->match($payment)?->category_id)->toBe($this->food->id)
        ->and($this->matcher->match($refund))->toBeNull()
        ->and($this->matcher->backfill($rule))->toBe(1)
        ->and($refund->fresh()->category_id)->toBeNull();
});
