<?php

use App\Models\MailAccount;
use App\Models\Transaction;
use App\Models\User;

test('the amount field keeps digits only, drops leading zeros and groups thousands', function () {
    $user = User::factory()->create();
    MailAccount::factory()->for($user)->create();

    $this->actingAs($user);

    $page = visit('/transactions/create');

    $page->type('#amount', '0')
        ->assertValue('#amount', '')
        ->type('#amount', '00a12.3-4 567')
        ->assertValue('#amount', '1.234.567')
        ->type('#counterparty', 'Cash')
        ->press('form button[type=submit]')
        ->assertPathBeginsWith('/transactions/')
        ->assertSee('1.234.567');

    expect(Transaction::sole()->amount)->toBe(1234567);
});

test('editing shows the saved amount grouped and saves the new one', function () {
    $user = User::factory()->create();
    MailAccount::factory()->for($user)->create();
    $manual = Transaction::factory()->for($user)->manual()->create(['amount' => 2500000]);

    $this->actingAs($user);

    visit(route('transactions.edit', $manual))
        ->assertValue('#amount', '2.500.000')
        ->clear('#amount')
        ->type('#amount', '30000')
        ->assertValue('#amount', '30.000')
        ->press('form button[type=submit]')
        ->assertPathIs("/transactions/{$manual->id}");

    expect($manual->fresh()->amount)->toBe(30000);
});

test('the amount field is focused when adding a transaction', function () {
    $user = User::factory()->create();
    MailAccount::factory()->for($user)->create();

    $this->actingAs($user);

    $page = visit('/transactions/create');

    expect($page->script("document.activeElement?.id"))->toBe('amount');
});

test('the amount field is not focused when editing a transaction', function () {
    $user = User::factory()->create();
    MailAccount::factory()->for($user)->create();
    $manual = Transaction::factory()->for($user)->manual()->create();

    $this->actingAs($user);

    $page = visit(route('transactions.edit', $manual));

    expect($page->script("document.activeElement?.id"))->not->toBe('amount');
});
