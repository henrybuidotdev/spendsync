<?php

namespace App\Livewire;

use App\Enums\MailAccountStatus;
use App\Mail\Inbox\InvalidCredentialsException;
use App\Mail\Inbox\MailboxClient;
use App\Mail\Inbox\MailboxConnectionException;
use App\Models\MailAccount;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ConnectGmail extends Component
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 600;

    public string $email = '';

    public string $appPassword = '';

    public function mount(): void
    {
        $this->email = (string) auth()->user()->mailAccount?->email;
    }

    public function connect(MailboxClient $client): void
    {
        $password = strtolower(preg_replace('/\s+/', '', $this->appPassword));
        $this->appPassword = '';
        $this->resetErrorBag();

        $key = 'connect-gmail:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->addError('appPassword', __('Too many attempts. Please try again in :minutes minutes.', [
                'minutes' => ceil(RateLimiter::availableIn($key) / 60),
            ]));

            return;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        $this->validate(['email' => ['required', 'email', 'max:255']]);

        if (! preg_match('/^[a-z]{16}$/', $password)) {
            $this->addError('appPassword', __('The app password is exactly 16 letters.'));

            return;
        }

        $email = strtolower(trim($this->email));

        try {
            $client->connect($email, $password);
        } catch (InvalidCredentialsException) {
            $this->addError('appPassword', __('Gmail rejected the sign-in. Check the Gmail address and the app password (not your normal Gmail password).'));

            return;
        } catch (MailboxConnectionException) {
            $this->addError('appPassword', __('Couldn\'t reach Gmail. Please try again in a few minutes.'));

            return;
        } finally {
            $client->disconnect();
        }

        $account = $this->storeAccount($email, $password);

        RateLimiter::clear($key);
        $account->queueSync();

        $this->redirectRoute('transactions', navigate: true);
    }

    private function storeAccount(string $email, #[\SensitiveParameter] string $password): MailAccount
    {
        $user = auth()->user();
        $account = $user->mailAccount ?? $user->mailAccounts()->make();

        if ($account->email !== $email) {
            $account->last_synced_at = null;
            $account->last_run_at = null;
        }

        $account->fill([
            'provider' => 'gmail',
            'email' => $email,
            'app_password' => $password,
            'status' => MailAccountStatus::Active,
            'last_error' => null,
        ])->save();

        return $account;
    }

    public function render()
    {
        return view('livewire.connect-gmail', [
            'reconnecting' => auth()->user()->mailAccount !== null,
        ])->title(__('Connect Gmail'));
    }
}
