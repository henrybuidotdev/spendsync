<?php

use App\Enums\Locale;
use App\Livewire\ConnectGmail;
use App\Livewire\Reports;
use App\Livewire\Settings;
use App\Livewire\Transactions\Form as TransactionsForm;
use App\Livewire\Transactions\Index as TransactionsIndex;
use App\Livewire\Transactions\Show as TransactionsShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('locale/{locale}', function (Locale $locale) {
    auth()->user()?->settings()->updateOrCreate([], ['locale' => $locale]);

    return redirect()->back(fallback: route('home'))->withCookie(cookie()->forever('locale', $locale->value));
})->name('locale');
Route::get('privacy', fn () => view('privacy.'.app()->getLocale()))->name('privacy');
Route::get('terms', fn () => view('terms.'.app()->getLocale()))->name('terms');

Route::middleware('auth')->group(function () {
    Route::get('connect', ConnectGmail::class)->name('connect');
    Route::post('demo', function () {
        auth()->user()->update(['viewing_demo' => true]);

        return redirect()->route('transactions');
    })->name('demo.start');
    Route::delete('demo', function () {
        auth()->user()->update(['viewing_demo' => false]);

        return redirect()->route('transactions');
    })->name('demo.stop');
    Route::get('settings/{section?}', Settings::class)->whereIn('section', Settings::SECTIONS)->name('settings');
    Route::view('profile', 'profile')->name('profile');
    Route::get('transactions/create', TransactionsForm::class)->name('transactions.create');
    Route::get('transactions/{transaction}/edit', TransactionsForm::class)->name('transactions.edit')->withTrashed();
    Route::get('transactions', TransactionsIndex::class)->name('transactions');
    Route::get('transactions/{transaction}', TransactionsShow::class)->name('transactions.show')->withTrashed();
    Route::get('reports', Reports::class)->name('reports');
});

require __DIR__.'/auth.php';

Route::fallback(fn () => abort(404));
