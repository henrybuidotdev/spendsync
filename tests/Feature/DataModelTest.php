<?php

use App\Enums\CategoryType;
use App\Enums\Direction;
use App\Livewire\Reports;
use App\Livewire\Settings;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\MailAccount;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SystemCategorySeeder;
use Illuminate\Support\Facades\DB;

test('every user shares the 44 system categories without private copies', function () {
    $user = User::factory()->create();

    expect($user->categories()->count())->toBe(0)
        ->and(Category::system()->count())->toBe(44)
        ->and($user->availableCategories()->count())->toBe(44)
        ->and($user->availableCategories()->where('type', 'income')->pluck('name')->all())->toBe(['Salary', 'Other income', 'Gifts', 'Sales', 'Refund'])
        ->and($user->availableCategories()->where('name', 'Food & drink')->value('icon'))->toBe('🍜');
});

test('users see system categories and their own, never another users', function () {
    $user = User::factory()->create();
    $own = Category::factory()->for($user)->create(['name' => 'Cà phê']);
    $foreign = Category::factory()->create(['name' => 'Bí mật']);

    $names = $user->availableCategories()->pluck('name');

    expect($names)->toHaveCount(45)->toContain('Cà phê')->not->toContain('Bí mật');
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

test('the system category seeder is safe to re-run and merges a user copy into a new default', function () {
    $user = User::factory()->create();
    $copy = Category::factory()->for($user)->create(['name' => 'Food & drink', 'type' => CategoryType::Expense]);
    $transaction = Transaction::factory()->for($user)->create(['category_id' => $copy->id]);
    Category::system()->where('name', 'Food & drink')->delete();

    $this->seed(SystemCategorySeeder::class);
    $this->seed(SystemCategorySeeder::class);

    $system = Category::system()->where('name', 'Food & drink')->sole();

    expect(Category::system()->count())->toBe(44)
        ->and(Category::find($copy->id))->toBeNull()
        ->and($transaction->fresh()->category_id)->toBe($system->id);
});

test('re-running the system category seeder never changes existing data', function () {
    $user = User::factory()->create();
    $system = Category::system()->where('name', 'Food & drink')->sole();
    $system->update(['icon' => '🍕']);
    $copy = Category::factory()->for($user)->create(['name' => 'Food & drink', 'type' => CategoryType::Expense]);

    $this->seed(SystemCategorySeeder::class);

    expect(Category::system()->count())->toBe(44)
        ->and($system->fresh()->icon)->toBe('🍕')
        ->and($copy->fresh())->not->toBeNull();
});

test('system categories are translated for display, a user category is shown as typed', function () {
    $user = User::factory()->create();
    $salary = Category::system()->where('name', 'Salary')->sole();
    $own = Category::factory()->for($user)->create(['name' => 'Salary']);

    app()->setLocale('vi');

    expect($salary->label)->toBe('Lương')
        ->and($own->label)->toBe('Salary');

    app()->setLocale('en');

    expect($salary->label)->toBe('Salary');
});

test('categories with a higher order come first', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Coffee', 'type' => CategoryType::Income]);

    $names = fn () => $user->availableCategories()->where('type', 'income')->ordered()->pluck('name')->all();

    expect($names())->toBe(['Salary', 'Gifts', 'Sales', 'Refund', 'Other income', 'Coffee']);

    Category::system()->where('name', 'Sales')->update(['order' => 100]);

    expect($names())->toBe(['Sales', 'Salary', 'Gifts', 'Refund', 'Other income', 'Coffee']);
});

test('a fresh install orders system categories exactly like the seeder', function () {
    $seeded = collect((new ReflectionClassConstant(SystemCategorySeeder::class, 'CATEGORIES'))->getValue())
        ->mapWithKeys(fn (array $category) => [$category[0] => $category[3]])
        ->sortKeys()
        ->all();

    expect(Category::system()->pluck('order', 'name')->sortKeys()->all())->toBe($seeded);
});

test('users can add a child category under a top-level category of the same type', function () {
    $user = User::factory()->create();
    $food = Category::system()->where('name', 'Food & drink')->first();
    $salary = Category::system()->where('name', 'Salary')->first();

    Livewire\Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->set('newName', 'Cà phê')
        ->set('newParentId', $salary->id)
        ->call('addCategory')
        ->assertHasErrors('newParentId')
        ->set('newParentId', $food->id)
        ->call('addCategory')
        ->assertHasNoErrors()
        ->assertDispatched('category-added');

    $coffee = $user->categories()->sole();

    Livewire\Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->set('newName', 'Latte')
        ->set('newParentId', $coffee->id)
        ->call('addCategory')
        ->assertHasErrors('newParentId');

    expect($coffee->parent_id)->toBe($food->id)
        ->and($coffee->fits(Direction::Out))->toBeTrue();
});

test('reports roll child category spending up into the parent', function () {
    $user = User::factory()->create();
    $food = Category::system()->where('name', 'Food & drink')->first();
    $coffee = Category::factory()->for($user)->create(['name' => 'Cà phê', 'parent_id' => $food->id]);
    Transaction::factory()->for($user)->create(['direction' => Direction::Out, 'amount' => 100, 'category_id' => $food->id, 'occurred_at' => now()]);
    Transaction::factory()->for($user)->create(['direction' => Direction::Out, 'amount' => 50, 'category_id' => $coffee->id, 'occurred_at' => now()]);

    $rows = Livewire\Livewire::actingAs($user)->test(Reports::class)->viewData('byCategory');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->category->id)->toBe($food->id)
        ->and($rows[0]->total)->toBe(150)
        ->and($rows[0]->count)->toBe(2);
});

test('the seeder nests child system categories under their parent', function () {
    $coffee = Category::system()->where('name', 'Coffee')->sole();

    expect($coffee->parent->name)->toBe('Food & drink')
        ->and(Category::system()->where('name', 'Home')->exists())->toBeFalse();
});
