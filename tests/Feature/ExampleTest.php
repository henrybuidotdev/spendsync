<?php

use App\Models\User;

test('guests see the home page with sign-up and supported banks', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Tự động đọc dữ liệu chi tiêu từ email ngân hàng.')
        ->assertSee(route('register'))
        ->assertSee(route('login'))
        ->assertSee('Vietcombank')
        ->assertSee('+14.608.000 ₫');
});

test('signed-in users can open the home page, which links to their transactions instead of sign-up', function () {
    $this->actingAs(User::factory()->create())->get('/')
        ->assertOk()
        ->assertSee('Xem giao dịch')
        ->assertSee(route('transactions'))
        ->assertDontSee(route('register'))
        ->assertDontSee(route('login'));
});

test('the privacy page is public', function () {
    $this->get('/privacy')->assertOk()->assertSee('<html lang="vi">', false)->assertSee('Quyền riêng tư');
});

test('the privacy page follows the chosen language', function () {
    $this->withCookie('locale', 'en')->get('/privacy')
        ->assertOk()
        ->assertSee('<html lang="en">', false)
        ->assertSee('What we read')
        ->assertDontSee('Chúng tôi đọc gì');
});

test('the PWA manifest and service worker are served', function () {
    expect(json_decode(file_get_contents(public_path('manifest.webmanifest')), true))->toHaveKey('start_url', '/transactions')
        ->and(file_exists(public_path('sw.js')))->toBeTrue();
});

test('the terms page is public, follows the chosen language and is linked from sign-up and settings', function () {
    $this->get('/terms')->assertOk()->assertSee('<html lang="vi">', false)->assertSee('Điều khoản sử dụng');

    $this->withCookie('locale', 'en')->get('/terms')->assertOk()->assertSee('Terms of use');

    $this->get('/register')->assertSee(route('terms'))->assertSee(route('privacy'));
});
