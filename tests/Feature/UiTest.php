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
use App\Livewire\Transactions\Form;
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

test('each day group shows the net total of its visible transactions', function () {
    $user = connectedUser();
    Transaction::factory()->for($user)->create(['amount' => 120000, 'occurred_at' => '2026-11-10 03:00:00']);
    Transaction::factory()->for($user)->create(['amount' => 30000, 'occurred_at' => '2026-11-10 04:00:00']);
    Transaction::factory()->for($user)->create(['amount' => 555000, 'occurred_at' => '2026-11-10 05:00:00'])->delete();
    Transaction::factory()->for($user)->income()->create(['amount' => 2000000, 'occurred_at' => '2026-11-11 03:00:00']);
    Transaction::factory()->for($user)->create(['amount' => 500000, 'occurred_at' => '2026-11-11 04:00:00']);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('−150.000 ₫')
        ->assertSee('+1.500.000 ₫');
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

test('sync now is hidden and ignored within 15 minutes of the last sync', function () {
    $fake = new FakeMailboxClient;
    app()->instance(MailboxClient::class, $fake);
    $user = connectedUser();
    $user->mailAccount->update(['last_synced_at' => now()->subMinutes(16)]);

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'gmail'])
        ->assertSeeHtml('wire:click="syncNow"')
        ->call('syncNow')
        ->assertSee('Có thể mất đến 10 phút');

    $this->travel(2)->minutes();

    Livewire::actingAs($user->fresh())->test(Settings::class, ['section' => 'gmail'])
        ->assertDontSeeHtml('wire:click="syncNow"')
        ->call('syncNow');

    expect($fake->searches)->toHaveCount(1);

    $this->travel(16)->minutes();

    Livewire::actingAs($user->fresh())->test(Settings::class, ['section' => 'gmail'])
        ->assertSeeHtml('wire:click="syncNow"')
        ->call('syncNow');

    expect($fake->searches)->toHaveCount(2);
});

test('sync now is hidden and ignored while a sync is running', function () {
    Queue::fake();
    $user = connectedUser();
    $user->mailAccounts()->first()->queueSync();
    Queue::assertPushed(SyncMailAccount::class, 1);

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'gmail'])
        ->assertSee('Đang đồng bộ')
        ->assertDontSeeHtml('wire:click="syncNow"')
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

test('the detail page shows parsed fields and the bank name', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create(['bank_code' => 'TCB', 'reference' => 'FT123']);

    $this->actingAs($user)->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('Techcombank')
        ->assertSee('FT123');
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

test('after choosing a category, the button fills in matching uncategorized transactions without creating a rule', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    $transaction = Transaction::factory()->for($user)->create(['counterparty' => 'BÚN BÒ HUẾ']);
    $sibling = Transaction::factory()->for($user)->create(['counterparty' => 'bun bo hue']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->assertDontSee('Tìm thấy')
        ->assertDontSee('Tự động lưu')
        ->call('setCategory', $food->id)
        ->assertSee('Tìm thấy 1 giao dịch với')
        ->assertSee('bạn có muốn lưu "Ăn uống" cho các giao dịch đó không?', false)
        ->assertSee('Tự động lưu "Ăn uống" cho các giao dịch trong tương lai với', false)
        ->assertSet('applyToFuture', false)
        ->call('applyToUncategorized')
        ->assertSee('Đã áp dụng cho 1 giao dịch.')
        ->assertDontSee('Tìm thấy');

    expect($sibling->fresh()->category_id)->toBe($food->id)
        ->and(CategoryRule::count())->toBe(0);
});

test('ticking the checkbox creates a rule without filling in past transactions', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    $transaction = Transaction::factory()->for($user)->create(['counterparty' => 'BÚN BÒ HUẾ']);
    $sibling = Transaction::factory()->for($user)->create(['counterparty' => 'bun bo hue']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->call('setCategory', $food->id)
        ->set('applyToFuture', true)
        ->assertSee('Đã lưu quy tắc.')
        ->assertSee('Quản lý quy tắc')
        ->assertSee(route('settings', 'rules'))
        ->assertSee('Tìm thấy 1 giao dịch với');

    expect($sibling->fresh()->category_id)->toBeNull()
        ->and(CategoryRule::sole()->match_value)->toBe('bun bo hue')
        ->and(CategoryRule::sole()->match_field)->toBe(MatchField::Counterparty);
});

test('the button is hidden when no uncategorized transaction matches', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create(['counterparty' => 'ONLY ONE']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->call('setCategory', $user->availableCategories()->first()->id)
        ->assertDontSee('Category saved')
        ->assertSee('Tự động lưu')
        ->assertDontSee('Tìm thấy');
});

test('choosing a category without ticking only categorizes this transaction', function () {
    $user = connectedUser();
    $transaction = Transaction::factory()->for($user)->create(['counterparty' => 'X']);
    $sibling = Transaction::factory()->for($user)->create(['counterparty' => 'X']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->call('setCategory', $user->availableCategories()->first()->id);

    expect(CategoryRule::count())->toBe(0)
        ->and($sibling->fresh()->category_id)->toBeNull();
});

test('the checkbox shows whether a rule exists and unticking removes the rule', function () {
    $user = connectedUser();
    $category = $user->availableCategories()->first();
    $transaction = Transaction::factory()->for($user)->create(['counterparty' => 'X']);
    $show = fn () => Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction->fresh()]);

    $show()->assertSet('applyToFuture', false)->call('setCategory', $category->id)->set('applyToFuture', true);

    expect(CategoryRule::count())->toBe(1);

    $show()->assertSet('applyToFuture', true)->set('applyToFuture', false)->assertSee('Đã xóa quy tắc.')->assertDontSee('Quản lý quy tắc');

    expect(CategoryRule::count())->toBe(0);

    $show()->assertSet('applyToFuture', false)->set('applyToFuture', true);

    expect(CategoryRule::sole()->category_id)->toBe($category->id);
});

test('a transaction in another category than the rule is an exception and never removes the rule', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    $bills = $user->availableCategories()->where('name', 'Bills')->first();
    $usual = Transaction::factory()->for($user)->create(['counterparty' => 'X']);
    $exception = Transaction::factory()->for($user)->create(['counterparty' => 'X']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $usual])->call('setCategory', $food->id)->call('applyToUncategorized')->set('applyToFuture', true);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $exception->fresh()])
        ->assertSet('applyToFuture', true)
        ->call('setCategory', $bills->id)
        ->assertSet('applyToFuture', false);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $exception->fresh()])
        ->assertSet('applyToFuture', false)
        ->set('applyToFuture', false);

    expect(CategoryRule::sole()->category_id)->toBe($food->id)
        ->and($exception->fresh()->category_id)->toBe($bills->id);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $usual->fresh()])->assertSet('applyToFuture', true);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $exception->fresh()])->set('applyToFuture', true);

    expect(CategoryRule::sole()->category_id)->toBe($bills->id);
});

test('ticking and unticking update the account rule, and the same account in another category opens unticked', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    $bills = $user->availableCategories()->where('name', 'Bills')->first();
    $inFood = Transaction::factory()->for($user)->create(['counterparty' => 'A', 'counterparty_account' => '0451000555666', 'category_id' => $food->id]);
    $inBills = Transaction::factory()->for($user)->create(['counterparty' => 'B', 'counterparty_account' => '0451000555666', 'category_id' => $bills->id]);
    $rule = fn () => CategoryRule::where('user_id', $user->id)->get(['match_field', 'match_value', 'category_id'])->toArray();
    $open = fn (Transaction $transaction) => Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction->fresh()]);

    $open($inFood)->assertSet('applyToFuture', false)->set('applyToFuture', true);

    expect($rule())->toBe([['match_field' => 'account', 'match_value' => '0451000555666', 'category_id' => $food->id]]);

    $open($inFood)->assertSet('applyToFuture', true);
    $open($inBills)->assertSet('applyToFuture', false)->set('applyToFuture', false);

    expect($rule())->toHaveCount(1);

    $open($inFood)->set('applyToFuture', false);

    expect($rule())->toBe([]);

    $open($inBills)->set('applyToFuture', true);

    expect($rule())->toBe([['match_field' => 'account', 'match_value' => '0451000555666', 'category_id' => $bills->id]]);

    $open($inFood)->assertSet('applyToFuture', false);
    $open($inBills)->assertSet('applyToFuture', true);
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
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
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

/* Hidden transactions */

test('hiding a transaction keeps it in the list but leaves it out of totals and reports', function () {
    $user = connectedUser();
    Transaction::factory()->for($user)->create(['amount' => 100000, 'counterparty' => 'GRAB', 'occurred_at' => '2026-11-03 03:00:00']);
    $hidden = Transaction::factory()->for($user)->create(['amount' => 700000, 'counterparty' => 'CHUYEN NOI BO', 'occurred_at' => '2026-11-04 03:00:00']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $hidden])
        ->call('toggleHidden')
        ->assertSee('Đã ẩn giao dịch.')
        ->assertSee('Giao dịch này đang bị ẩn');

    expect($hidden->fresh()->trashed())->toBeTrue();

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('CHUYEN NOI BO')
        ->assertSee('Đã ẩn')
        ->assertSee('100.000 ₫')
        ->assertDontSee('800.000 ₫');

    Livewire::actingAs($user)->test(Reports::class)
        ->assertSee('100.000 ₫')
        ->assertDontSee('CHUYEN NOI BO')
        ->assertDontSee('700.000 ₫');

    $this->actingAs($user)->get(route('transactions.show', $hidden))->assertOk()->assertSee('Hiện lại');

    Livewire::actingAs($user)->test(Show::class, ['transaction' => Transaction::withTrashed()->find($hidden->id)])
        ->call('toggleHidden')
        ->assertSee('Đã hiện lại giao dịch.')
        ->assertSee('Ẩn giao dịch');

    expect($hidden->fresh()->trashed())->toBeFalse();
});

test('users cannot hide another users transaction', function () {
    $foreign = Transaction::factory()->create();

    Livewire::actingAs(connectedUser())->test(Show::class, ['transaction' => $foreign])->assertForbidden();

    expect($foreign->fresh()->trashed())->toBeFalse();
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
        ->and(DB::table('categories')->whereNull('user_id')->count())->toBe(44);
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
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
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

    expect($user->availableCategories()->where('name', 'Lì xì')->value('icon'))->toBe('🧧')
        ->and($user->availableCategories()->where('type', 'income')->ordered()->pluck('name')->all())
        ->toBe(['Salary', 'Gifts', 'Sales', 'Refund', 'Lì xì', 'Other income']);
});

test('system categories are locked in settings and cannot be edited or deleted', function () {
    $user = connectedUser();
    $system = Category::system()->where('name', 'Food & drink')->first();

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertSee('Mặc định')
        ->assertDontSeeHtml("editCategory({$system->id})")
        ->assertDontSeeHtml("deleteCategory({$system->id})");

    foreach (['editCategory', 'deleteCategory'] as $method) {
        expect(fn () => Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])->call($method, $system->id))
            ->toThrow(ModelNotFoundException::class);
    }

    expect($system->fresh()->name)->toBe('Food & drink');
});

test('settings counts only the current users transactions for a system category', function () {
    $user = connectedUser();
    $food = Category::system()->where('name', 'Food & drink')->first();
    Transaction::factory()->for($user)->create(['category_id' => $food->id]);
    Transaction::factory()->count(5)->create(['category_id' => $food->id]);

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertViewHas('categories', fn ($categories) => $categories->firstWhere('id', $food->id)->transactions_count === 1);
});

test('the category picker only offers categories that fit the direction and opens on the matching tab', function () {
    $user = connectedUser();
    $out = Transaction::factory()->for($user)->create();
    $in = Transaction::factory()->for($user)->income()->create();
    $food = Category::system()->where('name', 'Food & drink')->first();
    $salary = Category::system()->where('name', 'Salary')->first();
    $loan = Category::system()->where('name', 'Lending')->first();
    $borrowing = Category::system()->where('name', 'Borrowing')->first();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $out])
        ->assertSeeHtml("setCategory({$food->id})")
        ->assertDontSeeHtml("setCategory({$borrowing->id})")
        ->assertDontSeeHtml("setCategory({$salary->id})")
        ->assertSeeHtml("setCategory({$loan->id})")
        ->assertSeeHtml("tab: 'expense' }");

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $in])
        ->assertSeeHtml("setCategory({$salary->id})")
        ->assertDontSeeHtml("setCategory({$food->id})")
        ->assertDontSeeHtml("setCategory({$loan->id})")
        ->assertSeeHtml("setCategory({$borrowing->id})")
        ->assertSeeHtml("tab: 'income' }");

    $out->update(['category_id' => $loan->id]);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $out->fresh()])
        ->assertSeeHtml("tab: 'debt_loan' }");
});

test('an income category cannot be assigned to money out', function () {
    $user = connectedUser();
    $out = Transaction::factory()->for($user)->create();
    $salary = Category::system()->where('name', 'Salary')->first();

    expect(fn () => Livewire::actingAs($user)->test(Show::class, ['transaction' => $out])->call('setCategory', $salary->id))
        ->toThrow(ModelNotFoundException::class);

    expect($out->fresh()->category_id)->toBeNull();
});

test('settings lists one category type at a time and adds new categories as that type', function () {
    $user = connectedUser();

    $component = Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->assertSet('categoryType', 'expense')
        ->assertSee('Ăn uống')
        ->assertDontSee('Lương')
        ->assertDontSeeHtml('wire:model="categoryType"')
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

test('users cannot add their own debt or loan category', function () {
    $user = connectedUser();

    Livewire::actingAs($user)->test(Settings::class, ['section' => 'categories'])
        ->call('showCategoryType', 'debt_loan')
        ->assertSee('Đi vay')
        ->assertDontSeeHtml('wire:submit="addCategory"')
        ->set('newName', 'Họ')
        ->call('addCategory')
        ->assertHasErrors('categoryType');

    expect($user->categories()->count())->toBe(0);
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
    Transaction::factory()->for($user)->create()->delete();
    CategoryRule::factory()->for($user)->create(['category_id' => $own->id]);
    $other = connectedUser();
    Transaction::factory()->for($other)->create();

    Volt::actingAs($user)->test('profile.delete-data-form')
        ->set('password', 'password')
        ->call('deleteData')
        ->assertHasNoErrors()
        ->assertRedirect(route('connect'));

    expect($user->fresh())->not->toBeNull()
        ->and($user->transactions()->withTrashed()->count())->toBe(0)
        ->and($user->rawEmails()->count())->toBe(0)
        ->and($user->categoryRules()->count())->toBe(0)
        ->and($user->categories()->count())->toBe(0)
        ->and($user->mailAccounts()->count())->toBe(0)
        ->and($other->transactions()->count())->toBe(1)
        ->and($other->mailAccounts()->count())->toBe(1)
        ->and(Category::system()->count())->toBe(44);

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

/* Manual transactions */

test('adding a manual transaction saves it in UTC and opens its details', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();

    $component = Livewire::actingAs($user)->test(Form::class)
        ->set('amount', '50.000')
        ->set('counterparty', ' Cà phê ')
        ->set('occurredAt', '2026-11-12T08:30')
        ->call('setCategory', $food->id)
        ->call('save')
        ->assertHasNoErrors();

    $transaction = Transaction::sole();

    $component->assertRedirect(route('transactions.show', $transaction));

    expect($transaction->isManual())->toBeTrue()
        ->and($transaction->raw_email_id)->toBeNull()
        ->and($transaction->amount)->toBe(50000)
        ->and($transaction->direction)->toBe(Direction::Out)
        ->and($transaction->counterparty)->toBe('Cà phê')
        ->and($transaction->category_id)->toBe($food->id)
        ->and($transaction->occurred_at->toDateTimeString())->toBe('2026-11-12 01:30:00');

    $this->get(route('transactions.show', $transaction))->assertSee('Nhập thủ công');
});

test('a manual transaction rejects a zero amount and a category of the other kind', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();

    foreach (['0', '0123', '12,50', '-5.000', '1,000', 'abc'] as $amount) {
        Livewire::actingAs($user)->test(Form::class)
            ->set('amount', $amount)
            ->set('counterparty', 'Cash')
            ->call('save')
            ->assertHasErrors('amount');
    }

    Livewire::actingAs($user)->test(Form::class)
        ->call('setDirection', 'in')
        ->set('categoryId', $food->id)
        ->set('amount', '100000')
        ->set('counterparty', 'Cash')
        ->call('save')
        ->assertHasErrors('categoryId');

    expect(Transaction::count())->toBe(0);
});

test('a new manual transaction without a category follows existing rules', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    CategoryRule::create(['user_id' => $user->id, 'match_field' => MatchField::Counterparty, 'match_value' => 'bun bo hue', 'category_id' => $food->id]);

    Livewire::actingAs($user)->test(Form::class)
        ->set('amount', '1.045.000')
        ->set('counterparty', 'Bún bò Huế')
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::sole()->category_id)->toBe($food->id)
        ->and(Transaction::sole()->amount)->toBe(1045000);
});

test('only your own manual transactions can be edited', function () {
    $user = connectedUser();
    $manual = Transaction::factory()->for($user)->manual()->create(['amount' => 10000]);

    Livewire::actingAs($user)->test(Form::class, ['transaction' => $manual])
        ->assertSet('amount', '10.000')
        ->set('amount', '25.000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('transactions.show', $manual));

    expect($manual->fresh()->amount)->toBe(25000);

    $synced = Transaction::factory()->for($user)->create();
    $this->actingAs($user)->get(route('transactions.edit', $synced))->assertNotFound();

    $foreign = Transaction::factory()->manual()->create();
    $this->actingAs($user)->get(route('transactions.edit', $foreign))->assertForbidden();
});

test('a manual transaction can be deleted, a synced one cannot', function () {
    $user = connectedUser();
    $manual = Transaction::factory()->for($user)->manual()->create();
    $synced = Transaction::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $manual])
        ->assertSee('Xóa')
        ->call('destroy')
        ->assertRedirect(route('transactions', ['month' => '2026-11']));

    expect(Transaction::withTrashed()->find($manual->id))->toBeNull();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $synced])
        ->call('destroy')
        ->assertNotFound();

    expect($synced->fresh())->not->toBeNull();
});

test('users without Gmail can start with manual transactions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect('/connect');
    $this->actingAs($user)->get('/connect')->assertSee(route('transactions.create'));
    $this->actingAs($user)->get('/transactions/create')->assertOk()->assertSee('Thêm giao dịch');

    Transaction::factory()->for($user)->manual()->create();

    $this->actingAs($user)->get('/')->assertOk();
});

test('each debt/loan category fits one direction', function () {
    $categories = Category::system()->where('type', 'debt_loan')->get();

    expect($categories->pluck('name')->sort()->values()->all())->toBe(collect(Category::DEBT_LOAN_DIRECTIONS)->keys()->sort()->values()->all());

    foreach ($categories as $category) {
        $direction = Category::DEBT_LOAN_DIRECTIONS[$category->name];
        $other = $direction === Direction::In ? Direction::Out : Direction::In;

        expect($category->fits($direction))->toBeTrue()
            ->and($category->fits($other))->toBeFalse()
            ->and(Category::fitting($direction)->whereKey($category->id)->exists())->toBeTrue()
            ->and(Category::fitting($other)->whereKey($category->id)->exists())->toBeFalse();
    }
});

test('a manual transaction needs a counterparty and a valid date', function (string $field, string $value) {
    $user = connectedUser();

    Livewire::actingAs($user)->test(Form::class)
        ->set('amount', '50.000')
        ->set('counterparty', 'Cash')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);

    expect(Transaction::count())->toBe(0);
})->with([
    'empty counterparty' => ['counterparty', ''],
    'blank counterparty' => ['counterparty', '   '],
    'too long counterparty' => ['counterparty', str_repeat('a', 256)],
    'missing date' => ['occurredAt', ''],
    'not a date' => ['occurredAt', 'yesterday'],
    'impossible date' => ['occurredAt', '2026-13-01T10:00'],
    'too long note' => ['note', str_repeat('a', 1001)],
]);

test('a manual transaction can be money in, and a blank note is not saved', function () {
    $user = connectedUser();
    $salary = Category::system()->where('name', 'Salary')->first();

    Livewire::actingAs($user)->test(Form::class)
        ->call('setDirection', 'in')
        ->set('amount', '10.000.000')
        ->set('counterparty', 'Company')
        ->set('note', '   ')
        ->call('setCategory', $salary->id)
        ->call('save')
        ->assertHasNoErrors();

    $transaction = Transaction::sole();

    expect($transaction->direction)->toBe(Direction::In)
        ->and($transaction->signedAmount())->toBe(10000000)
        ->and($transaction->category_id)->toBe($salary->id)
        ->and($transaction->note)->toBeNull();
});

test('the note of a manual transaction is trimmed', function () {
    Livewire::actingAs(connectedUser())->test(Form::class)
        ->set('amount', '50.000')
        ->set('counterparty', 'Cash')
        ->set('note', '  lunch with team  ')
        ->call('save');

    expect(Transaction::sole()->note)->toBe('lunch with team');
});

test('a category picked by hand wins over a matching rule', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    $shopping = $user->availableCategories()->where('name', 'Shopping')->first();
    CategoryRule::create(['user_id' => $user->id, 'match_field' => MatchField::Counterparty, 'match_value' => 'bun bo hue', 'category_id' => $food->id]);

    Livewire::actingAs($user)->test(Form::class)
        ->set('amount', '45.000')
        ->set('counterparty', 'Bún bò Huế')
        ->call('setCategory', $shopping->id)
        ->call('save');

    expect(Transaction::sole()->category_id)->toBe($shopping->id);
});

test('switching direction clears a category that no longer fits, and tapping a category again unselects it', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    $lending = Category::system()->where('name', 'Lending')->first();

    Livewire::actingAs($user)->test(Form::class)
        ->call('setCategory', $food->id)
        ->assertSet('categoryId', $food->id)
        ->call('setCategory', $food->id)
        ->assertSet('categoryId', null)
        ->call('setCategory', $lending->id)
        ->call('setDirection', 'in')
        ->assertSet('direction', Direction::In)
        ->assertSet('categoryId', null)
        ->call('setDirection', 'sideways')
        ->assertSet('direction', Direction::Out);
});

test('the form only offers categories that fit the chosen direction', function () {
    $user = connectedUser();
    $food = Category::system()->where('name', 'Food & drink')->first();
    $salary = Category::system()->where('name', 'Salary')->first();
    $lending = Category::system()->where('name', 'Lending')->first();
    $borrowing = Category::system()->where('name', 'Borrowing')->first();

    Livewire::actingAs($user)->test(Form::class)
        ->assertSeeHtml("setCategory({$food->id})")
        ->assertSeeHtml("setCategory({$lending->id})")
        ->assertDontSeeHtml("setCategory({$salary->id})")
        ->assertDontSeeHtml("setCategory({$borrowing->id})")
        ->call('setDirection', 'in')
        ->assertSeeHtml("setCategory({$salary->id})")
        ->assertSeeHtml("setCategory({$borrowing->id})")
        ->assertDontSeeHtml("setCategory({$food->id})")
        ->assertDontSeeHtml("setCategory({$lending->id})");
});

test('editing a manual transaction can change direction, date and category, or clear the category', function () {
    $user = connectedUser();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    $salary = Category::system()->where('name', 'Salary')->first();
    $manual = Transaction::factory()->for($user)->manual()->create([
        'amount' => 50000,
        'counterparty' => 'Cash',
        'category_id' => $food->id,
        'note' => 'old note',
        'occurred_at' => '2026-11-10 03:00:00',
    ]);

    Livewire::actingAs($user)->test(Form::class, ['transaction' => $manual])
        ->assertSet('direction', Direction::Out)
        ->assertSet('counterparty', 'Cash')
        ->assertSet('categoryId', $food->id)
        ->assertSet('note', 'old note')
        ->assertSet('occurredAt', '2026-11-10T10:00')
        ->call('setDirection', 'in')
        ->assertSet('categoryId', null)
        ->call('setCategory', $salary->id)
        ->set('occurredAt', '2026-11-11T20:15')
        ->set('counterparty', 'Refund')
        ->set('note', '')
        ->call('save')
        ->assertHasNoErrors();

    $manual->refresh();

    expect($manual->direction)->toBe(Direction::In)
        ->and($manual->category_id)->toBe($salary->id)
        ->and($manual->counterparty)->toBe('Refund')
        ->and($manual->note)->toBeNull()
        ->and($manual->occurred_at->toDateTimeString())->toBe('2026-11-11 13:15:00')
        ->and(Transaction::count())->toBe(1);

    Livewire::actingAs($user)->test(Form::class, ['transaction' => $manual])
        ->call('setCategory', $salary->id)
        ->call('save');

    expect($manual->fresh()->category_id)->toBeNull();
});

test('saving an edit checks ownership again', function () {
    $owner = connectedUser();
    $manual = Transaction::factory()->for($owner)->manual()->create(['amount' => 10000]);

    $component = Livewire::actingAs($owner)->test(Form::class, ['transaction' => $manual]);

    $this->actingAs(connectedUser());

    $component->set('amount', '99.000')->call('save')->assertForbidden();

    expect($manual->fresh()->amount)->toBe(10000);
});

test('a hidden manual transaction can be edited and stays hidden', function () {
    $user = connectedUser();
    $hidden = Transaction::factory()->for($user)->manual()->create(['amount' => 10000]);
    $hidden->delete();

    $this->actingAs($user)->get(route('transactions.edit', $hidden))->assertOk();

    Livewire::actingAs($user)->test(Form::class, ['transaction' => $hidden])
        ->set('amount', '20.000')
        ->call('save')
        ->assertHasNoErrors();

    $hidden = Transaction::withTrashed()->find($hidden->id);

    expect($hidden->amount)->toBe(20000)
        ->and($hidden->trashed())->toBeTrue();
});

test('another user cannot delete your manual transaction', function () {
    $owner = connectedUser();
    $manual = Transaction::factory()->for($owner)->manual()->create();

    $component = Livewire::actingAs($owner)->test(Show::class, ['transaction' => $manual]);

    $this->actingAs(connectedUser());

    $component->call('destroy')->assertForbidden();

    expect($manual->fresh())->not->toBeNull();
});

test('a hidden manual transaction can be deleted', function () {
    $user = connectedUser();
    $hidden = Transaction::factory()->for($user)->manual()->create();
    $hidden->delete();

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $hidden])->call('destroy');

    expect(Transaction::withTrashed()->find($hidden->id))->toBeNull();
});

test('edit and delete buttons only show on manual transactions', function () {
    $user = connectedUser();
    $manual = Transaction::factory()->for($user)->manual()->create();
    $synced = Transaction::factory()->for($user)->create();

    $this->actingAs($user)->get(route('transactions.show', $manual))
        ->assertSee(route('transactions.edit', $manual))
        ->assertSeeHtml('wire:click="destroy"');

    $this->actingAs($user)->get(route('transactions.show', $synced))
        ->assertDontSee(route('transactions.edit', $synced))
        ->assertDontSeeHtml('wire:click="destroy"')
        ->assertSee('Vietcombank (VCB)');
});

test('the transactions page links to the add form', function () {
    $this->actingAs(connectedUser())->get('/')->assertSee(route('transactions.create'));
});

test('guests cannot add, edit or delete transactions', function () {
    $manual = Transaction::factory()->manual()->create();

    $this->get('/transactions/create')->assertRedirect('/login');
    $this->get(route('transactions.edit', $manual))->assertRedirect('/login');
    $this->get(route('transactions.show', $manual))->assertRedirect('/login');
});

test('the category picker shows the selected category with its parent and opens the tree in a modal', function () {
    $user = connectedUser();
    $coffee = Category::system()->where('name', 'Coffee')->first();
    $transaction = Transaction::factory()->for($user)->create(['category_id' => $coffee->id]);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => $transaction])
        ->assertSeeHtml('aria-haspopup="dialog"')
        ->assertSeeInOrder(['Ăn uống', 'Cà phê', 'Khoản chi']);

    Livewire::actingAs($user)->test(Show::class, ['transaction' => Transaction::factory()->for($user)->create()])
        ->assertSee('Chọn danh mục');
});
