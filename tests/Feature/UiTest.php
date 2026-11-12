<?php

use App\Enums\Direction;
use App\Enums\Locale;
use App\Enums\MailAccountStatus;
use App\Enums\MatchField;
use App\Enums\Theme;
use App\Jobs\ParseRawEmail;
use App\Jobs\SyncMailAccount;
use App\Livewire\ConnectGmail;
use App\Livewire\Reports;
use App\Livewire\Settings;
use App\Livewire\Transactions\Index;
use App\Livewire\Transactions\Show;
use App\Mail\Inbox\FakeMailboxClient;
use App\Mail\Inbox\MailboxClient;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\MailAccount;
use App\Models\RawEmail;
use App\Models\Transaction;
use App\Models\User;
use App\Parsing\ParserRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 05:00:00', 'UTC'));
});

function connectedUser(): User
{
    $user = User::factory()->create();
    MailAccount::factory()->for($user)->create();

    return $user;
}

/* Onboarding / connect */

test('users without a mailbox are redirected to connect', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/connect');
    $this->actingAs(User::factory()->create())->get('/reports')->assertRedirect('/connect');
});

test('the connect page shows the guide, trust box and supported banks', function () {
    $this->actingAs(User::factory()->create())
        ->get('/connect')
        ->assertOk()
        ->assertSee('https://myaccount.google.com/apppasswords')
        ->assertSee('Chúng tôi chỉ đọc email từ ngân hàng')
        ->assertSee('Vietcombank')
        ->assertSee('Techcombank')
        ->assertSee('MB Bank');
});

test('connecting with a valid app password saves the account and starts a sync', function () {
    Queue::fake();
    $fake = new FakeMailboxClient;
    app()->instance(MailboxClient::class, $fake);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(ConnectGmail::class)
        ->set('email', 'Me@Gmail.com')
        ->set('appPassword', 'abcd efgh ijkl mnop')
        ->call('connect')
        ->assertHasNoErrors()
        ->assertSet('appPassword', '')
        ->assertRedirect(route('transactions'));

    $account = $user->fresh()->mailAccount;

    expect($account->email)->toBe('me@gmail.com')
        ->and($account->app_password)->toBe('abcdefghijklmnop')
        ->and($account->status)->toBe(MailAccountStatus::Active);

    Queue::assertPushed(SyncMailAccount::class, fn ($job) => $job->account->is($account));
});

test('a rejected app password shows a Vietnamese error and never echoes the password', function () {
    app()->instance(MailboxClient::class, new FakeMailboxClient(failAuth: true));
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(ConnectGmail::class)
        ->set('email', 'me@gmail.com')
        ->set('appPassword', 'wxyzwxyzwxyzwxyz')
        ->call('connect')
        ->assertHasErrors('appPassword')
        ->assertSet('appPassword', '')
        ->assertSee('Gmail từ chối đăng nhập');

    expect(json_encode($component->errors()->toArray()))->not->toContain('wxyzwxyzwxyzwxyz')
        ->and($component->html())->not->toContain('wxyzwxyzwxyzwxyz')
        ->and($user->mailAccounts()->count())->toBe(0);
});

test('a malformed app password is rejected before contacting Gmail', function () {
    $fake = new FakeMailboxClient;
    app()->instance(MailboxClient::class, $fake);

    Livewire::actingAs(User::factory()->create())->test(ConnectGmail::class)
        ->set('email', 'me@gmail.com')
        ->set('appPassword', 'my-normal-password1')
        ->call('connect')
        ->assertHasErrors('appPassword')
        ->assertSet('appPassword', '');

    expect($fake->connectedAs)->toBeNull();
});

test('the connect form is rate limited to 5 attempts per 10 minutes', function () {
    app()->instance(MailboxClient::class, new FakeMailboxClient(failAuth: true));
    $component = Livewire::actingAs(User::factory()->create())->test(ConnectGmail::class)->set('email', 'me@gmail.com');

    foreach (range(1, 5) as $attempt) {
        $component->set('appPassword', 'abcdabcdabcdabcd')->call('connect')->assertSee('Gmail từ chối đăng nhập');
    }

    $component->set('appPassword', 'abcdabcdabcdabcd')->call('connect')->assertSee('Bạn đã thử quá nhiều lần');

    $this->travel(11)->minutes();

    $component->set('appPassword', 'abcdabcdabcdabcd')->call('connect')->assertSee('Gmail từ chối đăng nhập');
});

test('reconnecting reactivates the existing account', function () {
    Queue::fake();
    app()->instance(MailboxClient::class, new FakeMailboxClient);
    $account = MailAccount::factory()->needsReconnect()->create(['email' => 'me@gmail.com']);

    Livewire::actingAs($account->user)->test(ConnectGmail::class)
        ->assertSet('email', 'me@gmail.com')
        ->set('appPassword', 'newpnewpnewpnewp')
        ->call('connect');

    expect(MailAccount::count())->toBe(1)
        ->and($account->fresh()->status)->toBe(MailAccountStatus::Active)
        ->and($account->fresh()->app_password)->toBe('newpnewpnewpnewp');
});

/* Transactions list */

test('the list shows month totals, formatted amounts and day groups', function () {
    $user = connectedUser();
    Transaction::factory()->for($user)->create(['amount' => 1250000, 'counterparty' => 'TRAN THI BICH', 'occurred_at' => '2026-11-12 03:00:00']);
    Transaction::factory()->for($user)->income()->create(['amount' => 5000000, 'counterparty' => 'CONG TY ABC', 'occurred_at' => '2026-11-05 03:00:00']);
    Transaction::factory()->for($user)->create(['amount' => 999000, 'counterparty' => 'THANG TRUOC', 'occurred_at' => '2026-10-20 03:00:00']);

    $this->actingAs($user)->get('/')
        ->assertOk()
        ->assertSee('Tháng 11/2026')
        ->assertSee('TRAN THI BICH')
        ->assertSee('−1.250.000 ₫')
        ->assertSee('+5.000.000 ₫')
        ->assertSee('+3.750.000 ₫')
        ->assertSee('Chưa phân loại')
        ->assertDontSee('THANG TRUOC');

    Livewire::actingAs($user)->test(Index::class)
        ->call('previousMonth')
        ->assertSet('month', '2026-10')
        ->assertSee('THANG TRUOC')
        ->assertDontSee('TRAN THI BICH');
});

test('month boundaries follow Vietnam time', function () {
    $user = connectedUser();
    Transaction::factory()->for($user)->create(['counterparty' => 'DAU THANG', 'occurred_at' => '2026-10-31 17:30:00']);

    Livewire::actingAs($user)->test(Index::class, ['month' => '2026-11'])->assertSee('DAU THANG');
    Livewire::actingAs($user)->test(Index::class, ['month' => '2026-10'])->assertDontSee('DAU THANG');
});

test('filters narrow the list', function () {
    $user = connectedUser();
    $category = $user->availableCategories()->first();
    Transaction::factory()->for($user)->create(['counterparty' => 'RA CO DANH MUC', 'category_id' => $category->id]);
    Transaction::factory()->for($user)->create(['counterparty' => 'RA CHUA PHAN LOAI']);
    Transaction::factory()->for($user)->income()->create(['counterparty' => 'VAO']);

    Livewire::actingAs($user)->test(Index::class)
        ->call('setFilter', 'uncategorized')
        ->assertSee('RA CHUA PHAN LOAI')->assertDontSee('RA CO DANH MUC')
        ->call('setFilter', 'in')
        ->assertSee('VAO')->assertDontSee('RA CHUA PHAN LOAI')
        ->call('setFilter', 'out')
        ->assertSee('RA CO DANH MUC')->assertDontSee('>VAO<', false);
});

test('the list never shows another users transactions', function () {
    $user = connectedUser();
    Transaction::factory()->create(['counterparty' => 'NGUOI KHAC']);

    $this->actingAs($user)->get('/')->assertDontSee('NGUOI KHAC');
});

test('the list query count does not grow with the number of transactions', function () {
    $user = connectedUser();
    $categories = $user->availableCategories()->get();
    Transaction::factory()->for($user)->count(3)->create(['category_id' => $categories[0]->id]);

    DB::enableQueryLog();
    Livewire::actingAs($user->fresh())->test(Index::class);
    $few = count(DB::getQueryLog());

    Transaction::factory()->for($user)->count(30)->sequence(fn ($sequence) => ['category_id' => $categories[$sequence->index % 10]->id])->create();

    DB::flushQueryLog();
    Livewire::actingAs($user->fresh())->test(Index::class);

    expect(count(DB::getQueryLog()))->toBe($few);
});

test('sync now runs a sync at most once per minute', function () {
    $fake = new FakeMailboxClient;
    app()->instance(MailboxClient::class, $fake);
    $user = connectedUser();
    $user->mailAccount->update(['last_synced_at' => now()]);

    $component = Livewire::actingAs($user)->test(Index::class)->call('syncNow')->assertDontSee('Đang đồng bộ');
    $component->call('syncNow')->assertSee('Vừa đồng bộ xong');

    expect($fake->searches)->toHaveCount(1);

    $this->travel(61)->seconds();
    $component->call('syncNow');

    expect($fake->searches)->toHaveCount(2);
});

test('sync now is hidden and ignored while a sync is running', function () {
    Queue::fake();
    $user = connectedUser();
    $user->mailAccounts()->first()->queueSync();
    Queue::assertPushed(SyncMailAccount::class, 1);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('Đang đồng bộ')
        ->assertDontSee('Đồng bộ ngay')
        ->call('syncNow');

    Queue::assertPushed(SyncMailAccount::class, 1);
});

test('needs_reconnect accounts get a red banner linking to connect', function () {
    $account = MailAccount::factory()->needsReconnect()->create();

    $this->actingAs($account->user)->get('/')
        ->assertSee('Mất kết nối Gmail')
        ->assertSee(route('connect'));
});

test('a brand-new connected user sees a friendly empty state', function () {
    $this->actingAs(connectedUser())->get('/')->assertSee('Chưa có giao dịch nào');
});

/* Transaction detail */

test('the detail page shows parsed fields and the bank name but hides the balance', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create(['bank_code' => 'TCB', 'reference' => 'FT123', 'balance_after' => 8150000]);

    $this->actingAs($user)->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('Techcombank')
        ->assertSee('FT123')
        ->assertDontSee('8.150.000 ₫');
});

test('the detail page links back to the list tab it was opened from', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create(['occurred_at' => '2026-11-12 03:00:00']);

    Livewire::actingAs($user)->test(Index::class, ['month' => '2026-11'])
        ->call('setFilter', 'uncategorized')
        ->assertSee(route('transactions.show', [$transaction, 'filter' => 'uncategorized']), false);

    $this->actingAs($user)->get(route('transactions.show', [$transaction, 'filter' => 'uncategorized']))
        ->assertSee(route('transactions', ['month' => '2026-11', 'filter' => 'uncategorized']));

    $this->actingAs($user)->get(route('transactions.show', [$transaction, 'filter' => 'bogus']))
        ->assertSee(route('transactions', ['month' => '2026-11']).'"', false);
});

test('users cannot open or change another users transaction', function () {
    $user = connectedUser();
    $foreign = Transaction::factory()->create();

    $this->actingAs($user)->get(route('transactions.show', $foreign))->assertForbidden();
});

test('users cannot assign another users category', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create();
    $foreignCategory = Category::factory()->create();

    expect(fn () => Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])->call('setCategory', $foreignCategory->id))
        ->toThrow(ModelNotFoundException::class);

    expect($transaction->fresh()->category_id)->toBeNull();
});

test('choosing a category with the checkbox creates a rule and backfills', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Ăn uống')->first();
    $transaction = Transaction::factory()->for($user)->create(['counterparty' => 'BÚN BÒ HUẾ']);
    $sibling = Transaction::factory()->for($user)->create(['counterparty' => 'bun bo hue']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->assertSet('applyToFuture', true)
        ->assertSee('Áp dụng cho các giao dịch sau với')
        ->call('setCategory', $food->id)
        ->assertSee('Đã lưu danh mục và áp dụng cho 1 giao dịch khác.');

    expect($transaction->fresh()->category_id)->toBe($food->id)
        ->and($sibling->fresh()->category_id)->toBe($food->id)
        ->and(CategoryRule::first()->match_value)->toBe('bun bo hue')
        ->and(CategoryRule::first()->match_field)->toBe(MatchField::Counterparty);
});

test('unticking the checkbox only categorizes this transaction', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create(['counterparty' => 'X']);
    $sibling = Transaction::factory()->for($user)->create(['counterparty' => 'X']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->set('applyToFuture', false)
        ->call('setCategory', $user->availableCategories()->first()->id);

    expect(CategoryRule::count())->toBe(0)
        ->and($sibling->fresh()->category_id)->toBeNull();
});

test('notes save automatically, trimmed, and clearing removes them', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->set('note', '  ăn trưa với team ')
        ->assertHasNoErrors()
        ->assertDispatched('note-saved')
        ->set('note', '')
        ->assertDispatched('note-saved');

    expect($transaction->fresh()->note)->toBeNull();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->set('note', 'ăn trưa với team');

    expect($transaction->fresh()->note)->toBe('ăn trưa với team');
});

test('the original email is shown as escaped text only', function () {
    $user = connectedUser();
    $rawEmail = RawEmail::factory()->for($user)->create([
        'body_html' => '<p>Số tiền: -50,000 VND</p><p>&lt;script&gt;alert(1)&lt;/script&gt;</p>',
    ]);
    $transaction = Transaction::factory()->for($user)->create(['raw_email_id' => $rawEmail->id]);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->assertDontSee('Số tiền: -50,000 VND')
        ->call('toggleRaw')
        ->assertSee('Số tiền: -50,000 VND')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertDontSeeHtml('<p>Số tiền');
});

/* Reports */

test('reports rank spending by category with percentages and top counterparties', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Ăn uống')->first();
    Transaction::factory()->for($user)->create(['amount' => 300000, 'category_id' => $food->id, 'counterparty' => 'QUAN AN', 'occurred_at' => '2026-11-02 03:00:00']);
    Transaction::factory()->for($user)->create(['amount' => 100000, 'counterparty' => 'GRAB', 'occurred_at' => '2026-11-03 03:00:00']);
    Transaction::factory()->for($user)->income()->create(['amount' => 9000000, 'occurred_at' => '2026-11-04 03:00:00']);

    Livewire::actingAs($user)->test(Reports::class)
        ->assertSee('400.000 ₫')
        ->assertSee('Ăn uống')
        ->assertSee('75%')
        ->assertSee('25%')
        ->assertSee('QUAN AN')
        ->assertDontSee('9.000.000 ₫')
        ->call('previousMonth')
        ->assertSee('Chưa có khoản chi nào trong tháng này');
});

/* Settings */

test('disconnecting deletes the app password but keeps transactions', function () {
    $user = connectedUser();
    Transaction::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Settings::class)->call('disconnect');

    $account = $user->mailAccount()->first();
    expect($account->app_password)->toBeNull()
        ->and($account->status)->toBe(MailAccountStatus::Disabled)
        ->and($user->transactions()->count())->toBe(1);
});

test('categories can be added, renamed and deleted', function () {
    $user = connectedUser();
    $category = Category::factory()->for($user)->create(['icon' => '🐱']);
    $transaction = Transaction::factory()->for($user)->create(['category_id' => $category->id]);

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->set('newName', 'Thú cưng')->set('newIcon', '🐶')
        ->call('addCategory')->assertHasNoErrors()
        ->call('editCategory', $category->id)->assertSet('editingIcon', $category->icon)
        ->set('editingName', 'Ăn ngoài')->set('editingIcon', '☕')
        ->call('renameCategory')
        ->assertSee('☕')
        ->call('deleteCategory', $category->id);

    expect($user->availableCategories()->where('name', 'Thú cưng')->value('icon'))->toBe('🐶')
        ->and($category->fresh())->toBeNull()
        ->and($transaction->fresh()->category_id)->toBeNull();
});

test('settings cannot touch another users categories or rules', function () {
    $user = connectedUser();
    $other = User::factory()->create();
    $foreignCategory = Category::factory()->for($other)->create();
    $foreignRule = CategoryRule::factory()->for($other)->create(['category_id' => $foreignCategory->id]);

    foreach ([['deleteCategory', $foreignCategory->id], ['editCategory', $foreignCategory->id], ['deleteRule', $foreignRule->id]] as [$method, $id]) {
        expect(fn () => Livewire::actingAs($user)->test(Settings::class)->call($method, $id))->toThrow(ModelNotFoundException::class);
    }

    expect($foreignCategory->fresh())->not->toBeNull()
        ->and($foreignRule->fresh())->not->toBeNull();
});

test('rules are listed and can be deleted', function () {
    $user = connectedUser();
    $rule = CategoryRule::factory()->for($user)->create(['match_value' => 'Grab', 'category_id' => $user->availableCategories()->first()->id]);

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'rules'])
        ->assertSee('grab')
        ->call('deleteRule', $rule->id);

    expect($rule->fresh())->toBeNull();
});

test('the language switch is saved to the user settings', function () {
    $user = connectedUser();

    Livewire::actingAs($user)->test(Settings::class)->call('setLocale', 'en')->assertRedirect(route('settings', 'appearance'));

    expect($user->settings()->first()->locale)->toBe(Locale::En);

    $this->actingAs($user->fresh())->get('/')
        ->assertSee('Transactions')
        ->assertSee('Sync now')
        ->assertCookie('locale', 'en');
});

test('the theme switch is saved to the user settings and rendered on every page', function () {
    $user = connectedUser();

    Livewire::actingAs($user)->test(Settings::class)->call('setTheme', 'dark');

    expect($user->settings()->first()->theme)->toBe(Theme::Dark);

    $this->actingAs($user->fresh())->get('/')
        ->assertSee("window.appTheme = 'dark'", false)
        ->assertCookie('theme', 'dark');
});

test('unknown settings values are ignored', function () {
    $user = connectedUser();

    Livewire::actingAs($user)->test(Settings::class)->call('setLocale', 'fr')->call('setTheme', 'neon');

    expect($user->settings()->exists())->toBeFalse();
});

test('users who never changed a setting get the defaults', function () {
    $user = connectedUser();

    $this->actingAs($user)->get('/')
        ->assertSee('Giao dịch')
        ->assertSee("window.appTheme = 'system'", false);
});

test('logged-out pages use the last settings from the cookies', function () {
    $this->withCookie('locale', 'en')->withCookie('theme', 'dark')->get('/login')
        ->assertSee('Log in')
        ->assertDontSee('Đăng nhập')
        ->assertSee("window.appTheme = 'dark'", false);
});

test('deleting all data removes the user and everything they own', function () {
    $user = connectedUser();
    $rawEmail = RawEmail::factory()->for($user)->create();
    Transaction::factory()->for($user)->create(['raw_email_id' => $rawEmail->id]);
    CategoryRule::factory()->for($user)->create(['category_id' => $user->availableCategories()->first()->id]);

    Volt::actingAs($user)->test('profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    foreach (['users', 'mail_accounts', 'raw_emails', 'transactions', 'category_rules'] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }

    expect(DB::table('categories')->whereNotNull('user_id')->count())->toBe(0)
        ->and(DB::table('categories')->whereNull('user_id')->count())->toBe(20);
});

test('every main page renders for a populated account', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create(['direction' => Direction::Out]);

    foreach (['/', '/reports', '/settings', '/connect', '/profile', route('transactions.show', $transaction)] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

test('an empty month offers a jump to the latest month with data', function () {
    $user = connectedUser();
    Transaction::factory()->for($user)->create(['counterparty' => 'CU', 'occurred_at' => '2026-08-10 03:00:00']);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('Xem tháng 08/2026')
        ->set('month', '2026-08')
        ->assertSee('CU');
});

test('the syncing banner shows only while a user-started sync is pending and polls', function () {
    Queue::fake();
    $user = connectedUser();
    $account = $user->mailAccount;
    $account->update(['last_synced_at' => now()]);

    $this->actingAs($user)->get('/')->assertDontSee('Đang đồng bộ');

    $account->queueSync();

    $this->actingAs($user)->get('/')->assertSee('Đang đồng bộ')->assertSee('wire:poll.3s', false);

    app()->instance(MailboxClient::class, new FakeMailboxClient);
    (new SyncMailAccount($account))->handle(app(MailboxClient::class), app(ParserRegistry::class));

    $this->actingAs($user)->get('/')->assertDontSee('Đang đồng bộ');
});

test('the syncing flag clears even when the sync fails', function () {
    $account = MailAccount::factory()->create();
    $account->queueSync();
    app()->instance(MailboxClient::class, new FakeMailboxClient(failAuth: true));

    SyncMailAccount::dispatchSync($account);

    expect($account->isSyncing())->toBeFalse();
});

test('rows show the category emoji, its initial when it has no emoji, or ? when uncategorized', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Ăn uống')->first();
    $coffee = $user->availableCategories()->create(['name' => 'cà phê', 'icon' => null, 'type' => 'expense']);
    Transaction::factory()->for($user)->create(['category_id' => $food->id]);
    Transaction::factory()->for($user)->create(['category_id' => $coffee->id]);

    $this->actingAs($user)->get('/')
        ->assertSee('🍜')
        ->assertSeeHtml('aria-hidden="true">C</span>')
        ->assertDontSeeHtml('aria-hidden="true">?</span>');

    Transaction::factory()->for($user)->create();

    $this->actingAs($user)->get('/')->assertSeeHtml('aria-hidden="true">?</span>');
});

test('settings offers an emoji picker and an icon can be removed', function () {
    $user = connectedUser();
    $category = Category::factory()->for($user)->create(['icon' => '🐱']);

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertSee('Chọn biểu tượng')
        ->assertSeeHtml("\$wire.newIcon = '☕'")
        ->call('editCategory', $category->id)
        ->set('editingIcon', '')
        ->call('renameCategory');

    expect($category->fresh()->icon)->toBeNull();
});

test('the emoji picker offers spending and income groups', function () {
    $user = connectedUser();

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertSee('Chi tiêu')
        ->assertSee('Thu nhập')
        ->assertSeeHtml("\$wire.newIcon = '🧧'")
        ->assertSeeHtml("\$wire.newIcon = '🥖'")
        ->call('showCategoryType', 'income')
        ->set('newName', 'Lì xì')->set('newIcon', '🧧')
        ->call('addCategory')
        ->assertHasNoErrors();

    expect($user->availableCategories()->where('name', 'Lì xì')->value('icon'))->toBe('🧧');
});

test('system categories are locked in settings and cannot be edited or deleted', function () {
    $user = connectedUser();
    $system = Category::system()->where('name', 'Ăn uống')->first();

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertSee('Mặc định')
        ->assertDontSeeHtml("editCategory({$system->id})")
        ->assertDontSeeHtml("deleteCategory({$system->id})");

    foreach (['editCategory', 'deleteCategory'] as $method) {
        expect(fn () => Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])->call($method, $system->id))
            ->toThrow(ModelNotFoundException::class);
    }

    expect($system->fresh()->name)->toBe('Ăn uống');
});

test('settings counts only the current users transactions for a system category', function () {
    $user = connectedUser();
    $food = Category::system()->where('name', 'Ăn uống')->first();
    Transaction::factory()->for($user)->create(['category_id' => $food->id]);
    Transaction::factory()->count(5)->create(['category_id' => $food->id]);

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertViewHas('categories', fn ($categories) => $categories->firstWhere('id', $food->id)->transactions_count === 1);
});

test('the category picker offers every type and opens on the tab for the transaction direction', function () {
    $user = connectedUser();
    $out = Transaction::factory()->for($user)->create();
    $in = Transaction::factory()->for($user)->income()->create();
    $food = Category::system()->where('name', 'Ăn uống')->first();
    $salary = Category::system()->where('name', 'Lương')->first();
    $loan = Category::system()->where('name', 'Cho vay')->first();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $out])
        ->assertSeeHtml("setCategory({$food->id})")
        ->assertSeeHtml("setCategory({$salary->id})")
        ->assertSeeHtml("setCategory({$loan->id})")
        ->assertViewHas('defaultTab', 'expense');

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $in])
        ->assertViewHas('defaultTab', 'income');

    $out->update(['category_id' => $loan->id]);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $out->fresh()])
        ->assertViewHas('defaultTab', 'debt_loan');
});

test('a category from another tab can be assigned', function () {
    $user = connectedUser();
    $out = Transaction::factory()->for($user)->create();
    $salary = Category::system()->where('name', 'Lương')->first();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $out])->call('setCategory', $salary->id);

    expect($out->fresh()->category_id)->toBe($salary->id);
});

test('settings lists one category type at a time and adds new categories as that type', function () {
    $user = connectedUser();

    $component = Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertSet('categoryType', 'expense')
        ->assertSee('Ăn uống')
        ->assertDontSee('Lương')
        ->assertDontSeeHtml('<select')
        ->set('newName', 'Cà phê')
        ->call('addCategory');

    $component->call('showCategoryType', 'income')
        ->assertSee('Lương')
        ->assertDontSee('Ăn uống')
        ->assertDontSee('Cà phê')
        ->set('newName', 'Lì xì')
        ->call('addCategory')
        ->assertSee('Lì xì');

    expect($user->categories()->pluck('type', 'name')->map->value->all())->toBe(['Cà phê' => 'expense', 'Lì xì' => 'income']);

    $component->call('showCategoryType', 'nonsense')->assertSet('categoryType', 'expense');
});

test('the danger zone on the profile page offers delete data and delete account separately', function () {
    $this->actingAs(connectedUser())->get('/profile')
        ->assertSee('Vùng nguy hiểm')
        ->assertSee('Xóa dữ liệu')
        ->assertSee('Xóa tài khoản')
        ->assertDontSee('Xóa toàn bộ dữ liệu</');
});

test('delete data needs the login password and keeps everything on a wrong one', function () {
    $user = connectedUser();
    Transaction::factory()->for($user)->create();

    Volt::actingAs($user)->test('profile.delete-data-form')
        ->set('password', 'wrong-password')
        ->call('deleteData')
        ->assertHasErrors('password');

    expect($user->transactions()->count())->toBe(1)
        ->and($user->mailAccounts()->count())->toBe(1);
});

test('delete data wipes the users data but keeps the login, other users and system categories', function () {
    $user = connectedUser();
    $own = Category::factory()->for($user)->create();
    $rawEmail = RawEmail::factory()->for($user)->create();
    Transaction::factory()->for($user)->create(['raw_email_id' => $rawEmail->id, 'category_id' => $own->id]);
    CategoryRule::factory()->for($user)->create(['category_id' => $own->id]);
    $other = connectedUser();
    Transaction::factory()->for($other)->create();

    Volt::actingAs($user)->test('profile.delete-data-form')
        ->set('password', 'password')
        ->call('deleteData')
        ->assertHasNoErrors()
        ->assertRedirect(route('connect'));

    expect($user->fresh())->not->toBeNull()
        ->and($user->transactions()->count())->toBe(0)
        ->and($user->rawEmails()->count())->toBe(0)
        ->and($user->categoryRules()->count())->toBe(0)
        ->and($user->categories()->count())->toBe(0)
        ->and($user->mailAccounts()->count())->toBe(0)
        ->and($other->transactions()->count())->toBe(1)
        ->and($other->mailAccounts()->count())->toBe(1)
        ->and(Category::system()->count())->toBe(20);

    $this->actingAs($user)->get('/')->assertRedirect('/connect');
});

test('sync and parse jobs are configured to drop themselves when their model was deleted', function () {
    expect((new SyncMailAccount(MailAccount::factory()->make()))->deleteWhenMissingModels)->toBeTrue()
        ->and((new ParseRawEmail(RawEmail::factory()->make()))->deleteWhenMissingModels)->toBeTrue();
});

test('settings opens as a short menu and each part has its own page', function () {
    $user = connectedUser();

    $this->actingAs($user)->get('/settings')
        ->assertOk()
        ->assertSee(route('settings', 'categories'))
        ->assertSee(route('settings', 'rules'))
        ->assertDontSee('Thêm khoản chi')
        ->assertDontSee('Xóa dữ liệu');

    foreach (Settings::SECTIONS as $section) {
        $this->actingAs($user)->get("/settings/{$section}")->assertOk()->assertSee(route('settings'));
    }

    $this->actingAs($user)->get('/settings/nope')->assertNotFound();
});
