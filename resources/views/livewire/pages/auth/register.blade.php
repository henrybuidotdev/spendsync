<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state([
    'name' => '',
    'email' => '',
    'password' => '',
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'password' => ['required', 'string', Rules\Password::defaults()],
]);

$register = function () {
    $validated = $this->validate();

    $validated['password'] = Hash::make($validated['password']);

    event(new Registered($user = User::create($validated)));

    Auth::login($user, remember: true);

    $this->redirect(route('transactions', absolute: false), navigate: true);
};

?>

<div>
    <x-auth-tabs />

    <form wire:submit="register" class="space-y-4">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="mt-1 block w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="mt-1 block w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input wire:model="password" class="mt-1" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <p class="text-xs text-slate-500">
            {{ __('By signing up, you agree to the') }}
            <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="underline hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950">{{ __('Privacy policy') }}</a>.
        </p>

        <x-primary-button class="w-full">{{ __('Register') }}</x-primary-button>
    </form>
</div>
