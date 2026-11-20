<?php

namespace Tests\Feature\Auth;

use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.register');
});

test('new users can register', function () {
    $component = Volt::test('pages.auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@spendsync.co')
        ->set('password', 'password');

    $component->call('register');

    $component->assertRedirect(route('transactions', absolute: false));

    $this->assertAuthenticated();
});

test('first-time visitors see Vietnamese', function () {
    $this->get('/login')->assertSee('lang="vi"', false)->assertSee('Đăng nhập');
});

test('a new account starts in Vietnamese even if the browser last used English', function () {
    Volt::test('pages.auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@spendsync.co')
        ->set('password', 'password')
        ->call('register');

    $this->withCookie('locale', 'en')->get('/connect')
        ->assertSee('<html lang="vi">', false)
        ->assertCookie('locale', 'vi');
});
