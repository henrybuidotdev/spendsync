<?php

use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\MailAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('every user shares the 20 Vietnamese system categories without private copies', function () {
    $user = User::factory()->create();

    expect($user->categories()->count())->toBe(0)
        ->and(Category::system()->count())->toBe(20)
        ->and($user->availableCategories()->count())->toBe(20)
        ->and($user->availableCategories()->where('type', 'income')->pluck('name')->all())->toBe(['Lương', 'Thu khác', 'Quà tặng', 'Bán hàng'])
        ->and($user->availableCategories()->where('name', 'Ăn uống')->value('icon'))->toBe('🍜');
});

test('users see system categories and their own, never another users', function () {
    $user = User::factory()->create();
    $own = Category::factory()->for($user)->create(['name' => 'Cà phê']);
    $foreign = Category::factory()->create(['name' => 'Bí mật']);

    $names = $user->availableCategories()->pluck('name');

    expect($names)->toHaveCount(21)->toContain('Cà phê')->not->toContain('Bí mật');
});

test('nobody can update or delete a system category, but everyone can view it', function () {
    $user = User::factory()->create();
    $system = Category::system()->first();

    expect($user->can('view', $system))->toBeTrue()
        ->and($user->can('update', $system))->toBeFalse()
        ->and($user->can('delete', $system))->toBeFalse();
});

test('app password is encrypted at rest and hidden from serialization', function () {
    $account = MailAccount::factory()->create(['app_password' => 'abcdefghijklmnop']);

    $stored = DB::table('mail_accounts')->where('id', $account->id)->value('app_password');

    expect($stored)->not->toContain('abcdefghijklmnop')
        ->and($account->fresh()->app_password)->toBe('abcdefghijklmnop')
        ->and($account->toArray())->not->toHaveKey('app_password')
        ->and($account->toJson())->not->toContain('abcdefghijklmnop');
});

test('category rule match values are stored lowercased and accent-stripped', function () {
    $rule = CategoryRule::factory()->create(['match_value' => '  NGUYỄN Văn Đức ']);

    expect($rule->match_value)->toBe('nguyen van duc');
});

test('deleting a category leaves its transactions uncategorized', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $transaction = Transaction::factory()->for($user)->create(['category_id' => $category->id]);

    $category->delete();

    expect($transaction->fresh()->category_id)->toBeNull();
});

test('user A cannot view, update or delete user B records', function (string $class) {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $record = match ($class) {
        Category::class => Category::factory()->for($userB)->create(),
        default => $class::factory()->for($userB)->create(),
    };

    expect($userA->can('view', $record))->toBeFalse()
        ->and($userA->can('update', $record))->toBeFalse()
        ->and($userA->can('delete', $record))->toBeFalse()
        ->and($userB->can('view', $record))->toBeTrue()
        ->and($userB->can('update', $record))->toBeTrue();
})->with([Transaction::class, Category::class, CategoryRule::class, MailAccount::class]);
