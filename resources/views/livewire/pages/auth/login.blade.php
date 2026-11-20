<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\form;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    $this->validate();

    $this->form->authenticate();

    Session::regenerate();

    $this->redirectIntended(default: route('transactions', absolute: false), navigate: true);
};

?>

<div>
    <x-auth-tabs />

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="mt-1 block w-full" type="email" name="email" required autofocus autocomplete="email" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-baseline justify-between gap-2">
                <x-input-label for="password" :value="__('Password')" />
                {{-- @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900 focus:outline-none focus-visible:underline">{{ __('Forgot your password?') }}</a>
                @endif --}}
            </div>
            <x-password-input wire:model="form.password" class="mt-1" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">{{ __('Log in') }}</x-primary-button>
    </form>
</div>
