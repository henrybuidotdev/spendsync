<?php

use App\Livewire\ConnectGmail;
use App\Livewire\Reports;
use App\Livewire\Settings;
use App\Livewire\Transactions\Index as TransactionsIndex;
use App\Livewire\Transactions\Show as TransactionsShow;
use Illuminate\Support\Facades\Route;

Route::view('privacy', 'privacy')->name('privacy');

Route::middleware('auth')->group(function () {
    Route::get('connect', ConnectGmail::class)->name('connect');
    Route::get('settings/{section?}', Settings::class)->whereIn('section', Settings::SECTIONS)->name('settings');
    Route::view('profile', 'profile')->name('profile');

    Route::middleware('connected')->group(function () {
        Route::get('/', TransactionsIndex::class)->name('transactions');
        Route::get('transactions/{transaction}', TransactionsShow::class)->name('transactions.show');
        Route::get('reports', Reports::class)->name('reports');
    });
});

require __DIR__.'/auth.php';
