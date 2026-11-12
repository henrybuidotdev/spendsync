<?php

use App\Enums\MailAccountStatus;
use App\Enums\MatchField;
use App\Jobs\ParseRawEmail;
use App\Jobs\SyncMailAccount;
use App\Mail\Inbox\FakeMailboxClient;
use App\Mail\Inbox\FetchedMessage;
use App\Mail\Inbox\MailboxClient;
use App\Mail\Inbox\MailboxConnectionException;
use App\Models\CategoryRule;
use App\Models\MailAccount;
use App\Models\RawEmail;
use App\Models\Transaction;
use App\Models\User;
use App\Parsing\ParserRegistry;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

function fixturesPath(): string
{
    return base_path('tests/Fixtures/emails');
}

function fakeMailbox(?FakeMailboxClient $fake = null): FakeMailboxClient
{
    $fake ??= FakeMailboxClient::fromDirectory(fixturesPath());
    app()->instance(MailboxClient::class, $fake);

    return $fake;
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));
});

test('sync fetches only configured senders, stores raw emails and parses them', function () {
    $fake = fakeMailbox();
    $account = MailAccount::factory()->create();

    SyncMailAccount::dispatchSync($account);

    expect($fake->searches)->toHaveCount(1)
        ->and($fake->searches[0]['senders'])->toBe(app(ParserRegistry::class)->senders())
        ->and($fake->searches[0]['since']->toDateString())->toBe(now()->subDays(SyncMailAccount::FIRST_SYNC_DAYS)->toDateString())
        ->and(RawEmail::count())->toBe(17)
        ->and(RawEmail::whereNull('parsed_at')->count())->toBe(0)
        ->and(Transaction::where('user_id', $account->user_id)->count())->toBe(13)
        ->and($account->fresh()->last_synced_at)->not->toBeNull()
        ->and($account->fresh()->last_error)->toBeNull();
});

test('a second sync starts one day before the last sync and creates no duplicates', function () {
    $fake = fakeMailbox();
    $account = MailAccount::factory()->create();

    SyncMailAccount::dispatchSync($account);
    $this->travel(5)->minutes();
    SyncMailAccount::dispatchSync($account);

    expect($fake->searches[1]['since']->toDateTimeString())->toBe('2026-11-11 12:00:00')
        ->and(RawEmail::count())->toBe(17)
        ->and(Transaction::count())->toBe(13);
});

test('the same email for two different users is stored for each', function () {
    fakeMailbox();
    $first = MailAccount::factory()->create();
    $second = MailAccount::factory()->create();

    SyncMailAccount::dispatchSync($first);
    SyncMailAccount::dispatchSync($second);

    expect(Transaction::where('user_id', $first->user_id)->count())->toBe(13)
        ->and(Transaction::where('user_id', $second->user_id)->count())->toBe(13);
});

test('invalid credentials mark the account as needs_reconnect', function () {
    fakeMailbox(new FakeMailboxClient(failAuth: true));
    $account = MailAccount::factory()->create();

    SyncMailAccount::dispatchSync($account);

    expect($account->fresh()->status)->toBe(MailAccountStatus::NeedsReconnect)
        ->and($account->fresh()->last_error)->toBe('invalid_credentials')
        ->and(RawEmail::count())->toBe(0);
});

test('connection failures keep the account active and rethrow for retry', function () {
    fakeMailbox(new FakeMailboxClient(failConnection: true));
    $account = MailAccount::factory()->create();

    expect(fn () => SyncMailAccount::dispatchSync($account))->toThrow(MailboxConnectionException::class);

    expect($account->fresh()->status)->toBe(MailAccountStatus::Active)
        ->and($account->fresh()->last_error)->toBe('connection_failed');
});

test('inactive or disconnected accounts are not synced', function () {
    $fake = fakeMailbox();
    $disabled = MailAccount::factory()->create(['status' => MailAccountStatus::Disabled]);
    $noPassword = MailAccount::factory()->create(['app_password' => null]);

    SyncMailAccount::dispatchSync($disabled);
    SyncMailAccount::dispatchSync($noPassword);

    expect($fake->searches)->toBeEmpty();
});

test('messages from unknown senders are never stored even if the mailbox returns them', function () {
    $rogue = new class extends FakeMailboxClient
    {
        public function fetchFrom(array $senders, $since): iterable
        {
            yield new FetchedMessage('<x@spam>', 'boss@company.com', 'Lương', CarbonImmutable::now(), null, 'Số tiền: +1,000 VND');
        }
    };
    fakeMailbox($rogue);

    SyncMailAccount::dispatchSync(MailAccount::factory()->create());

    expect(RawEmail::count())->toBe(0);
});

test('transaction times are stored in UTC', function () {
    $this->artisan('thuchi:import-eml', ['user' => User::factory()->create()->id, 'path' => fixturesPath().'/VCB/05_receipt_transfer.eml'])->assertSuccessful();

    expect(DB::table('transactions')->value('occurred_at'))->toBe('2026-10-15 12:09:00')
        ->and(Transaction::first()->occurred_at->timezone('Asia/Ho_Chi_Minh')->format('H:i'))->toBe('19:09');
});

test('import-eml imports a folder once and parses everything', function () {
    $user = User::factory()->create();

    $this->artisan('thuchi:import-eml', ['user' => $user->email, 'path' => fixturesPath()])
        ->expectsOutputToContain('Imported 17 of 17 files.')
        ->assertSuccessful();

    $this->artisan('thuchi:import-eml', ['user' => $user->email, 'path' => fixturesPath()])
        ->expectsOutputToContain('Imported 0 of 17 files.');

    expect($user->transactions()->count())->toBe(13)
        ->and($user->rawEmails()->whereNotNull('parse_error')->count())->toBe(0)
        ->and($user->transactions()->where('bank_code', 'MB')->sum('amount'))->toBe(2000000 + 150000 + 459000 + 1250000);
});

test('import-eml fails for unknown users', function () {
    $this->artisan('thuchi:import-eml', ['user' => 'nobody@x.test', 'path' => fixturesPath()])->assertFailed();
});

test('unparseable emails store a reason and log ids without the body', function () {
    Log::spy();
    $rawEmail = RawEmail::factory()->create([
        'from_address' => 'vcbdigibank@info.vietcombank.com.vn',
        'body_text' => "Số tiền: *** VND\nNội dung: BI MAT 123",
    ]);

    ParseRawEmail::dispatchSync($rawEmail);

    expect($rawEmail->fresh()->parse_error)->toBe('amount_missing')
        ->and($rawEmail->fresh()->parsed_at)->toBeNull()
        ->and(Transaction::count())->toBe(0);

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($rawEmail) {
        return $context['raw_email_id'] === $rawEmail->id
            && $context['bank_code'] === 'VCB'
            && ! str_contains(json_encode($context), 'BI MAT');
    });
});

test('emails without a parser are marked unsupported_sender', function () {
    $rawEmail = RawEmail::factory()->create(['from_address' => 'someone@else.vn']);

    ParseRawEmail::dispatchSync($rawEmail);

    expect($rawEmail->fresh()->parse_error)->toBe('unsupported_sender');
});

test('non-transaction bank emails are marked parsed with no transaction', function () {
    $user = User::factory()->create();

    $this->artisan('thuchi:import-eml', ['user' => $user->id, 'path' => fixturesPath().'/MB/90_otp.eml']);

    expect($user->rawEmails()->first()->parsed_at)->not->toBeNull()
        ->and($user->transactions()->count())->toBe(0);
});

test('reparse --failed fixes emails after a parser fix and keeps the category', function () {
    $user = User::factory()->create();
    $this->artisan('thuchi:import-eml', ['user' => $user->id, 'path' => fixturesPath().'/TCB']);
    $transaction = $user->transactions()->first();
    $transaction->update(['category_id' => $user->availableCategories()->first()->id, 'amount' => 1]);
    $user->rawEmails()->first()->update(['parse_error' => 'old_bug', 'parsed_at' => null]);

    $this->artisan('thuchi:reparse', ['--failed' => true])
        ->expectsOutputToContain('Reparsed 1 emails, 0 failed.')
        ->assertSuccessful();

    expect(RawEmail::whereNotNull('parse_error')->count())->toBe(0)
        ->and($user->transactions()->count())->toBe(3);

    $this->artisan('thuchi:reparse', ['--bank' => 'tcb'])->expectsOutputToContain('Reparsed 4 emails');

    expect($transaction->fresh()->amount)->not->toBe(1)
        ->and($transaction->fresh()->category_id)->toBe($user->availableCategories()->first()->id);
});

test('parse-stats shows 100% for the fixtures', function () {
    $this->artisan('thuchi:import-eml', ['user' => User::factory()->create()->id, 'path' => fixturesPath()]);

    $this->artisan('thuchi:parse-stats')
        ->expectsTable(['Bank', 'Total', 'Parsed', 'Failed', 'Success'], [
            ['MB', 5, 5, 0, '100%'],
            ['TCB', 4, 4, 0, '100%'],
            ['VCB', 8, 8, 0, '100%'],
        ])
        ->assertSuccessful();
});

test('thuchi:sync syncs active accounts now', function () {
    fakeMailbox();
    MailAccount::factory()->create();
    MailAccount::factory()->needsReconnect()->create();

    $this->artisan('thuchi:sync')->expectsOutputToContain('Synced 1 accounts.')->assertSuccessful();

    expect(Transaction::count())->toBe(13);
});

test('the scheduler queues a sync for each active account every five minutes', function () {
    Queue::fake();
    MailAccount::factory()->count(2)->create();
    MailAccount::factory()->needsReconnect()->create();

    $this->artisan('schedule:run');

    Queue::assertPushed(SyncMailAccount::class, 2);
});

test('parsed transactions get categorized by existing rules', function () {
    $user = User::factory()->create();
    $food = $user->availableCategories()->where('name', 'Ăn uống')->first();
    CategoryRule::create(['user_id' => $user->id, 'match_field' => MatchField::Description, 'match_value' => 'highlands coffee', 'category_id' => $food->id]);

    $this->artisan('thuchi:import-eml', ['user' => $user->id, 'path' => fixturesPath().'/VCB']);

    expect($user->transactions()->where('category_id', $food->id)->pluck('amount')->all())->toBe([89000]);
});

test('the demo seeder creates a populated, categorized demo account', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $user = User::where('email', 'demo@thuchi.test')->sole();

    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->transactions()->count())->toBe(13)
        ->and($user->transactions()->whereNotNull('category_id')->count())->toBe(6)
        ->and($user->rawEmails()->whereNotNull('parse_error')->count())->toBe(0);

    $this->actingAs($user)->get('/')->assertOk()->assertSee('PHAM MINH DUC');
});

test('the app password never appears in exception traces', function () {
    $client = new FakeMailboxClient(failConnection: true);

    try {
        $client->connect('me@gmail.com', 'supersecretpassw');
    } catch (MailboxConnectionException $exception) {
        expect($exception->getTraceAsString())->not->toContain('supersecretpassw')
            ->and($exception->getTrace()[0]['args'][1] ?? null)->not->toBe('supersecretpassw');

        return;
    }

    $this->fail('Expected a MailboxConnectionException.');
});
