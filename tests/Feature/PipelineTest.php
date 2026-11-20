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
use App\Parsing\Banks\VietcombankParser;
use App\Parsing\ParsedEmailInput;
use App\Parsing\ParserRegistry;
use App\Parsing\Support\ExchangeRates;
use App\Parsing\UnparseableEmailException;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

    expect($fake->searches[0]['senders'])->toBe(app(ParserRegistry::class)->senders())
        ->and(RawEmail::count())->toBe(16)
        ->and(RawEmail::whereNull('parsed_at')->count())->toBe(0)
        ->and(Transaction::where('user_id', $account->user_id)->count())->toBe(12)
        ->and($account->fresh()->last_synced_at)->not->toBeNull()
        ->and($account->fresh()->last_error)->toBeNull();
});

test('the first sync walks the last first_sync_days in 15-day windows, one queued job each', function () {
    $fake = fakeMailbox();
    $account = MailAccount::factory()->create();
    $account->queueSync();

    $windows = collect($fake->searches)->map(fn (array $search) => [
        $search['since']->toDateString(),
        $search['before']?->toDateString(),
    ]);

    expect($windows->all())->toBe([
        ['2026-08-14', '2026-08-29'],
        ['2026-08-29', '2026-09-13'],
        ['2026-09-13', '2026-09-28'],
        ['2026-09-28', '2026-10-13'],
        ['2026-10-13', '2026-10-28'],
        ['2026-10-28', '2026-11-12'],
        ['2026-11-12', null],
    ])
        ->and(RawEmail::count())->toBe(16)
        ->and($account->fresh()->last_synced_at->toDateTimeString())->toBe('2026-11-12 12:00:00')
        ->and($account->isSyncing())->toBeFalse();
});

test('an interrupted first sync resumes from the last finished window', function () {
    Queue::fake();
    fakeMailbox();
    $account = MailAccount::factory()->create();

    (new SyncMailAccount($account))->handle(app(MailboxClient::class), app(ParserRegistry::class));

    expect($account->fresh()->last_synced_at->toDateTimeString())->toBe('2026-08-29 00:00:00');
    Queue::assertPushed(SyncMailAccount::class, 1);
});

test('a second sync starts one day before the last sync and creates no duplicates', function () {
    $fake = fakeMailbox();
    $account = MailAccount::factory()->create();

    SyncMailAccount::dispatchSync($account);
    $this->travel(5)->minutes();
    SyncMailAccount::dispatchSync($account);

    expect(end($fake->searches)['since']->toDateTimeString())->toBe('2026-11-11 12:00:00')
        ->and(RawEmail::count())->toBe(16)
        ->and(Transaction::count())->toBe(12);
});

test('every window records when it ran, separately from how far it has got', function () {
    Queue::fake();
    fakeMailbox();
    $account = MailAccount::factory()->create();

    (new SyncMailAccount($account))->handle(app(MailboxClient::class), app(ParserRegistry::class));

    expect($account->fresh()->last_synced_at->toDateTimeString())->toBe('2026-08-29 00:00:00')
        ->and($account->fresh()->last_run_at->toDateTimeString())->toBe('2026-11-12 12:00:00');
});

test('a later sync goes back resync_days and walks forward to today in 15-day windows', function () {
    config(['services.mail_sync.resync_days' => 60]);
    $fake = fakeMailbox();
    $account = MailAccount::factory()->create(['last_synced_at' => now()->subHour()]);

    SyncMailAccount::dispatchSync($account);

    expect(collect($fake->searches)->map(fn (array $search) => [
        $search['since']->toDateString(),
        $search['before']?->toDateString(),
    ])->all())->toBe([
        ['2026-09-13', '2026-09-28'],
        ['2026-09-28', '2026-10-13'],
        ['2026-10-13', '2026-10-28'],
        ['2026-10-28', '2026-11-12'],
        ['2026-11-12', null],
    ])
        ->and($account->fresh()->last_synced_at->toDateTimeString())->toBe('2026-11-12 12:00:00');
});

test('an unfinished later sync never moves last_synced_at backwards', function () {
    config(['services.mail_sync.resync_days' => 365]);
    Queue::fake();
    fakeMailbox();
    $account = MailAccount::factory()->create(['last_synced_at' => now()->subHour()]);

    (new SyncMailAccount($account))->handle(app(MailboxClient::class), app(ParserRegistry::class));

    expect($account->fresh()->last_synced_at->toDateTimeString())->toBe('2026-11-12 11:00:00');
    Queue::assertPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->from->toDateString() === '2025-11-27');
});

test('the first sync goes back first_sync_days', function () {
    config(['services.mail_sync.first_sync_days' => 30]);
    $fake = fakeMailbox();
    $account = MailAccount::factory()->create();

    SyncMailAccount::dispatchSync($account);

    expect(collect($fake->searches)->map(fn (array $search) => [
        $search['since']->toDateString(),
        $search['before']?->toDateString(),
    ])->all())->toBe([
        ['2026-10-13', '2026-10-28'],
        ['2026-10-28', '2026-11-12'],
        ['2026-11-12', null],
    ]);
});

test('each window queues the next one starting where it ended', function () {
    Queue::fake();
    fakeMailbox();
    $account = MailAccount::factory()->create();

    (new SyncMailAccount($account))->handle(app(MailboxClient::class), app(ParserRegistry::class));

    Queue::assertPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->from->toDateTimeString() === '2026-08-29 00:00:00');
});

test('a continuation window starts at its from date, not resync_days before last_synced_at', function () {
    config(['services.mail_sync.resync_days' => 365]);
    $fake = fakeMailbox();
    $account = MailAccount::factory()->create(['last_synced_at' => now()->subHour()]);

    SyncMailAccount::dispatchSync($account, CarbonImmutable::parse('2026-11-01'));

    expect($fake->searches)->toHaveCount(1)
        ->and($fake->searches[0]['since']->toDateString())->toBe('2026-11-01')
        ->and($fake->searches[0]['before'])->toBeNull();
});

test('a sync that fails mid-walk keeps its progress and the next sync resumes from there', function () {
    $failing = new class extends FakeMailboxClient
    {
        public function connect(string $email, #[\SensitiveParameter] string $appPassword): void
        {
            if ($this->searches !== []) {
                throw new MailboxConnectionException('imap_connection_failed');
            }

            parent::connect($email, $appPassword);
        }
    };
    fakeMailbox($failing);
    $account = MailAccount::factory()->create();

    expect(fn () => SyncMailAccount::dispatchSync($account))->toThrow(MailboxConnectionException::class)
        ->and($account->fresh()->last_synced_at->toDateTimeString())->toBe('2026-08-29 00:00:00')
        ->and($account->fresh()->last_error)->toBe('connection_failed');

    $fake = fakeMailbox();

    SyncMailAccount::dispatchSync($account);

    expect($fake->searches[0]['since']->toDateString())->toBe('2026-08-28')
        ->and($account->fresh()->last_synced_at->toDateTimeString())->toBe('2026-11-12 12:00:00')
        ->and($account->fresh()->last_error)->toBeNull();
});

test('the same email for two different users is stored for each', function () {
    fakeMailbox();
    $first = MailAccount::factory()->create();
    $second = MailAccount::factory()->create();

    SyncMailAccount::dispatchSync($first);
    SyncMailAccount::dispatchSync($second);

    expect(Transaction::where('user_id', $first->user_id)->count())->toBe(12)
        ->and(Transaction::where('user_id', $second->user_id)->count())->toBe(12);
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
        public function fetchFrom(array $senders, $since, $before = null): iterable
        {
            yield new FetchedMessage('<x@spam>', 'boss@company.com', 'Lương', CarbonImmutable::now(), null, 'Số tiền: +1,000 VND');
        }
    };
    fakeMailbox($rogue);

    SyncMailAccount::dispatchSync(MailAccount::factory()->create());

    expect(RawEmail::count())->toBe(0);
});

test('transaction times are stored in UTC', function () {
    $this->artisan('spendsync:import-eml', ['user' => User::factory()->create()->id, 'path' => fixturesPath().'/VCB/05_receipt_transfer.eml'])->assertSuccessful();

    expect(DB::table('transactions')->value('occurred_at'))->toBe('2026-10-15 12:09:00')
        ->and(Transaction::first()->occurred_at->timezone('Asia/Ho_Chi_Minh')->format('H:i'))->toBe('19:09');
});

test('import-eml imports a folder once and parses everything', function () {
    $user = User::factory()->create();

    $this->artisan('spendsync:import-eml', ['user' => $user->email, 'path' => fixturesPath()])
        ->expectsOutputToContain('Imported 16 of 16 files.')
        ->assertSuccessful();

    $this->artisan('spendsync:import-eml', ['user' => $user->email, 'path' => fixturesPath()])
        ->expectsOutputToContain('Imported 0 of 16 files.');

    expect($user->transactions()->count())->toBe(12)
        ->and($user->rawEmails()->whereNotNull('parse_error')->count())->toBe(0)
        ->and($user->transactions()->where('bank_code', 'CAKE')->sum('amount'))->toBe(2000 + 2000 + 1250000);
});

test('re-parsing the email of a hidden transaction keeps it hidden instead of duplicating it', function () {
    $this->artisan('spendsync:import-eml', ['user' => User::factory()->create()->id, 'path' => fixturesPath().'/VCB/05_receipt_transfer.eml'])->assertSuccessful();
    Transaction::first()->delete();

    ParseRawEmail::dispatchSync(RawEmail::first());

    expect(Transaction::withTrashed()->count())->toBe(1)
        ->and(Transaction::count())->toBe(0);
});

test('import-eml fails for unknown users', function () {
    $this->artisan('spendsync:import-eml', ['user' => 'nobody@x.test', 'path' => fixturesPath()])->assertFailed();
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

    $this->artisan('spendsync:import-eml', ['user' => $user->id, 'path' => fixturesPath().'/CAKE/90_otp.eml']);

    expect($user->rawEmails()->first()->parsed_at)->not->toBeNull()
        ->and($user->transactions()->count())->toBe(0);
});

test('reparse --failed fixes emails after a parser fix and keeps the category', function () {
    $user = User::factory()->create();
    $this->artisan('spendsync:import-eml', ['user' => $user->id, 'path' => fixturesPath().'/CAKE']);
    $transaction = $user->transactions()->first();
    $transaction->update(['category_id' => $user->availableCategories()->first()->id, 'amount' => 1]);
    $user->rawEmails()->first()->update(['parse_error' => 'old_bug', 'parsed_at' => null]);

    $this->artisan('spendsync:reparse', ['--failed' => true])
        ->expectsOutputToContain('Reparsed 1 emails, 0 failed.')
        ->assertSuccessful();

    expect(RawEmail::whereNotNull('parse_error')->count())->toBe(0)
        ->and($user->transactions()->count())->toBe(3);

    $this->artisan('spendsync:reparse', ['--bank' => 'cake'])->expectsOutputToContain('Reparsed 4 emails');

    expect($transaction->fresh()->amount)->not->toBe(1)
        ->and($transaction->fresh()->category_id)->toBe($user->availableCategories()->first()->id);
});

test('parse-stats shows 100% for the fixtures', function () {
    $this->artisan('spendsync:import-eml', ['user' => User::factory()->create()->id, 'path' => fixturesPath()]);

    $this->artisan('spendsync:parse-stats')
        ->expectsTable(['Bank', 'Total', 'Parsed', 'Failed', 'Success'], [
            ['CAKE', 4, 4, 0, '100%'],
            ['VCB', 12, 12, 0, '100%'],
        ])
        ->assertSuccessful();
});

test('spendsync:sync syncs active accounts now', function () {
    fakeMailbox();
    MailAccount::factory()->create();
    MailAccount::factory()->needsReconnect()->create();

    $this->artisan('spendsync:sync')->expectsOutputToContain('Synced 1 accounts.')->assertSuccessful();

    expect(Transaction::count())->toBe(12);
});

test('the scheduler queues a sync for active accounts in the current minute slot that have not synced recently', function () {
    Queue::fake();
    $this->travelTo(now()->setTime(10, 0));
    MailAccount::factory()->create(['id' => 60]);
    MailAccount::factory()->create(['id' => 120, 'last_synced_at' => now()->subHour(), 'last_run_at' => now()->subHour()]);
    MailAccount::factory()->create(['id' => 180, 'last_synced_at' => now()->subMonth(), 'last_run_at' => now()->subMinutes(5)]);
    MailAccount::factory()->needsReconnect()->create(['id' => 240]);
    MailAccount::factory()->create(['id' => 61]);

    $this->artisan('schedule:run');

    Queue::assertPushed(SyncMailAccount::class, 2);
    Queue::assertPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->account->id === 60);
    Queue::assertPushed(SyncMailAccount::class, fn (SyncMailAccount $job) => $job->account->id === 120);
});

test('parsed transactions get categorized by existing rules', function () {
    $user = User::factory()->create();
    $food = $user->availableCategories()->where('name', 'Food & drink')->first();
    CategoryRule::create(['user_id' => $user->id, 'match_field' => MatchField::Description, 'match_value' => 'highlands coffee', 'category_id' => $food->id]);

    $this->artisan('spendsync:import-eml', ['user' => $user->id, 'path' => fixturesPath().'/VCB']);

    expect($user->transactions()->where('category_id', $food->id)->pluck('amount')->all())->toBe([89000]);
});

test('the demo seeder creates a populated, categorized demo account', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $user = User::where('email', 'demo@spendsync.test')->sole();

    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->transactions()->count())->toBe(12)
        ->and($user->transactions()->whereNotNull('category_id')->count())->toBe(3)
        ->and($user->rawEmails()->whereNotNull('parse_error')->count())->toBe(0);

    $this->actingAs($user)->get('/')->assertOk()->assertSee('TRAN THI BICH');
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

test('daily exchange rates let any currency parse and requeue emails that failed for a missing rate', function () {
    Queue::fake();
    Http::fake(['open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['USD' => 1, 'VND' => 26000, 'THB' => 32.5]])]);
    $stuck = RawEmail::factory()->create(['parse_error' => 'currency_unsupported']);
    $thb = fn () => (new VietcombankParser)->parse(ParsedEmailInput::make('x@bank.vn', 'x', null, "Số tiền: -95 THB\nNội dung: 7-ELEVEN", now()->toImmutable()));

    expect($thb)->toThrow(UnparseableEmailException::class, 'currency_unsupported');

    $this->artisan('spendsync:update-exchange-rates')
        ->expectsOutputToContain('Saved rates for 3 currencies.')
        ->assertSuccessful();

    expect($thb()->amount)->toBe(76000)
        ->and($thb()->amountEstimated)->toBeTrue();
    Queue::assertPushed(ParseRawEmail::class, fn ($job) => $job->rawEmail->is($stuck));
});

test('a failed exchange rate download keeps the previous rates', function () {
    ExchangeRates::save(['USD' => 25000.0]);
    Http::fake(['open.er-api.com/*' => Http::response('down', 503)]);

    $this->artisan('spendsync:update-exchange-rates')->assertFailed();

    expect(ExchangeRates::vndPer('USD'))->toBe(25000.0);
});
