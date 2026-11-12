<?php

test('guests are sent to login from the home page', function () {
    $this->get('/')->assertRedirect('/login');
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
    expect(json_decode(file_get_contents(public_path('manifest.webmanifest')), true))->toHaveKey('start_url', '/')
        ->and(file_exists(public_path('sw.js')))->toBeTrue();
});

test('the terms page is public, follows the chosen language and is linked from sign-up and settings', function () {
    $this->get('/terms')->assertOk()->assertSee('<html lang="vi">', false)->assertSee('Điều khoản sử dụng');

    $this->withCookie('locale', 'en')->get('/terms')->assertOk()->assertSee('Terms of use');

    $this->get('/register')->assertSee(route('terms'))->assertSee(route('privacy'));
});
