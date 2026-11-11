<?php

namespace App\Livewire;

use App\Enums\MailAccountStatus;
use App\Jobs\SyncMailAccount;
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
            $this->addError('appPassword', __('Bạn đã thử quá nhiều lần. Vui lòng thử lại sau :minutes phút.', [
                'minutes' => ceil(RateLimiter::availableIn($key) / 60),
            ]));

            return;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        $this->validate(['email' => ['required', 'email', 'max:255']]);

        if (! preg_match('/^[a-z]{16}$/', $password)) {
            $this->addError('appPassword', __('Mật khẩu ứng dụng gồm đúng 16 chữ cái.'));

            return;
        }

        $email = strtolower(trim($this->email));

        try {
            $client->connect($email, $password);
        } catch (InvalidCredentialsException) {
            $this->addError('appPassword', __('Gmail từ chối đăng nhập. Kiểm tra lại địa chỉ Gmail và mật khẩu ứng dụng (không phải mật khẩu Gmail thường).'));

            return;
        } catch (MailboxConnectionException) {
            $this->addError('appPassword', __('Không kết nối được tới Gmail. Vui lòng thử lại sau ít phút.'));

            return;
        } finally {
            $client->disconnect();
        }

        $account = $this->storeAccount($email, $password);

        RateLimiter::clear($key);
        SyncMailAccount::dispatch($account);

        session()->flash('syncing', true);
        $this->redirectRoute('transactions', navigate: true);
    }

    private function storeAccount(string $email, #[\SensitiveParameter] string $password): MailAccount
    {
        $user = auth()->user();
        $account = $user->mailAccount ?? $user->mailAccounts()->make();

        if ($account->email !== $email) {
            $account->last_synced_at = null;
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
            'banks' => config('banks'),
            'reconnecting' => auth()->user()->mailAccount !== null,
        ])->title(__('Kết nối Gmail'));
    }
}
