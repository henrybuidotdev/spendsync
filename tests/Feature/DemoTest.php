<?php

use App\Categorization\CategoryMatcher;
use App\Exceptions\DemoDataIsReadOnly;
use App\Livewire\Transactions\Form;
use App\Livewire\Transactions\Show;
use App\Models\Category;
use App\Models\CategoryRule;
use App\Models\RawEmail;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 05:00:00', 'UTC'));

    $this->demo = User::factory()->create(['email' => config('services.demo.email')]);
    $this->demoTransaction = Transaction::factory()->for($this->demo)->manual()->create(['counterparty' => 'Demo Coffee', 'note' => 'demo note']);
    $this->viewer = User::factory()->create(['viewing_demo' => true]);
});

test('a new user can switch to the demo data and back', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/transactions')->assertSee('Xem dữ liệu demo')->assertDontSee('Demo Coffee');

    $this->actingAs($user)->post('/demo')->assertRedirect(route('transactions'));

    expect($user->fresh()->viewing_demo)->toBeTrue();

    $this->actingAs($user->fresh())->get('/transactions')
        ->assertSee('Demo Coffee')
        ->assertSee('Bạn đang xem dữ liệu demo');
    $this->actingAs($user->fresh())->get('/reports')->assertSee('Bạn đang xem dữ liệu demo');
    $this->actingAs($user->fresh())->get('/settings/categories')->assertOk()->assertDontSee('Bạn đang xem dữ liệu demo');
    $this->actingAs($user->fresh())->get('/profile')->assertOk()->assertDontSee('Bạn đang xem dữ liệu demo');

    $this->actingAs($user->fresh())->delete('/demo')->assertRedirect(route('transactions'));

    expect($user->fresh()->viewing_demo)->toBeFalse();

    $this->actingAs($user->fresh())->get('/transactions')
        ->assertDontSee('Demo Coffee')
        ->assertDontSee('Bạn đang xem dữ liệu demo');
});

test('the demo button is hidden when no demo account exists', function () {
    $this->demo->delete();

    $this->actingAs(User::factory()->create())->get('/transactions')->assertDontSee('Xem dữ liệu demo');
});

test('demo transactions open for viewing and editing but changes are refused', function () {
    $this->actingAs($this->viewer)->get(route('transactions.show', $this->demoTransaction))->assertOk();
    $this->actingAs($this->viewer)->get(route('transactions.edit', $this->demoTransaction))->assertOk();

    Livewire::actingAs($this->viewer)->test(Show::class, ['transaction' => $this->demoTransaction])
        ->set('note', 'changed')
        ->assertDispatched('demo-read-only');

    Livewire::actingAs($this->viewer)->test(Show::class, ['transaction' => $this->demoTransaction])
        ->call('toggleHidden')
        ->assertDispatched('demo-read-only');

    Livewire::actingAs($this->viewer)->test(Show::class, ['transaction' => $this->demoTransaction])
        ->call('destroy')
        ->assertDispatched('demo-read-only')
        ->assertNoRedirect();

    Livewire::actingAs($this->viewer)->test(Form::class, ['transaction' => $this->demoTransaction])
        ->set('counterparty', 'Hacked')
        ->call('save')
        ->assertDispatched('demo-read-only')
        ->assertNoRedirect();

    $fresh = Transaction::withTrashed()->find($this->demoTransaction->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse()
        ->and($fresh->note)->toBe('demo note')
        ->and($fresh->counterparty)->toBe('Demo Coffee');
});

test('adding a transaction in demo mode is refused', function () {
    Livewire::actingAs($this->viewer)->test(Form::class)
        ->set('amount', '50.000')
        ->set('counterparty', 'New shop')
        ->call('save')
        ->assertDispatched('demo-read-only');

    expect(Transaction::count())->toBe(1);
});

test('bulk category writes on demo data are refused', function () {
    $category = Category::system()->first();
    $rule = CategoryRule::factory()->for($this->demo)->create(['category_id' => $category->id]);

    $this->actingAs($this->viewer);

    expect(fn () => app(CategoryMatcher::class)->backfill($rule))
        ->toThrow(DemoDataIsReadOnly::class)
        ->and(fn () => app(CategoryMatcher::class)->forget($this->demoTransaction))
        ->toThrow(DemoDataIsReadOnly::class);
});

test('signing in as the demo account itself cannot change demo data', function () {
    Livewire::actingAs($this->demo)->test(Show::class, ['transaction' => $this->demoTransaction])
        ->call('toggleHidden')
        ->assertDispatched('demo-read-only');

    expect($this->demoTransaction->fresh()->trashed())->toBeFalse();
});

test('outside demo mode other users transactions stay private', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('transactions.show', $this->demoTransaction))
        ->assertForbidden();
});

test('a demo viewer can still change their own settings', function () {
    $this->actingAs($this->viewer)->get('/locale/en')->assertRedirect();

    expect($this->viewer->fresh()->settings->locale->value)->toBe('en');
});

test('refresh-demo moves demo transactions forward by whole days so the newest is today', function () {
    Transaction::query()->forceDelete();
    $newest = Transaction::factory()->for($this->demo)->create(['occurred_at' => '2026-11-01 13:30:00']);
    $older = Transaction::factory()->for($this->demo)->create(['occurred_at' => '2026-10-20 02:00:00']);
    $hidden = Transaction::factory()->for($this->demo)->create(['occurred_at' => '2026-10-25 02:00:00']);
    $hidden->delete();
    $mine = Transaction::factory()->create(['occurred_at' => '2026-10-01 00:00:00']);
    $email = RawEmail::factory()->for($this->demo)->create(['received_at' => '2026-11-01 13:31:00']);
    $myEmail = RawEmail::factory()->create(['received_at' => '2026-10-01 00:00:00']);

    $this->artisan('spendsync:refresh-demo')->assertSuccessful();

    expect($newest->fresh()->occurred_at->toDateTimeString())->toBe('2026-11-12 13:30:00')
        ->and($older->fresh()->occurred_at->toDateTimeString())->toBe('2026-10-31 02:00:00')
        ->and(Transaction::withTrashed()->find($hidden->id)->occurred_at->toDateTimeString())->toBe('2026-11-05 02:00:00')
        ->and($mine->fresh()->occurred_at->toDateTimeString())->toBe('2026-10-01 00:00:00')
        ->and($email->fresh()->received_at->toDateTimeString())->toBe('2026-11-12 13:31:00')
        ->and($myEmail->fresh()->received_at->toDateTimeString())->toBe('2026-10-01 00:00:00');

    $this->artisan('spendsync:refresh-demo')->assertSuccessful();

    expect($newest->fresh()->occurred_at->toDateTimeString())->toBe('2026-11-12 13:30:00');
});

test('refresh-demo does nothing without a demo account', function () {
    $this->demo->delete();

    $this->artisan('spendsync:refresh-demo')->assertSuccessful();
});
