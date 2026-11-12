<?php

use App\Models\MailAccount;
use Illuminate\Support\Facades\DB;

use function Livewire\Volt\rules;
use function Livewire\Volt\state;

state(['password' => '']);

rules(['password' => ['required', 'string', 'current_password']])->attributes(['password' => __('mật khẩu')]);

/** Wipes everything except the login itself; the user starts over at /connect. */
$deleteData = function () {
    $this->validate();

    $user = auth()->user();

    DB::transaction(function () use ($user) {
        $user->transactions()->delete();
        $user->categoryRules()->delete();
        $user->categories()->delete();
        $user->rawEmails()->delete();
        $user->mailAccounts()->each(fn (MailAccount $account) => $account->finishSyncing());
        $user->mailAccounts()->delete();
    });

    $this->reset('password');

    session()->flash('status', __('Đã xóa toàn bộ dữ liệu. Tài khoản đăng nhập vẫn được giữ.'));
    $this->redirectRoute('connect', navigate: true);
};

?>

<section>
    <h3 class="text-sm font-semibold text-slate-900">{{ __('Xóa dữ liệu') }}</h3>
    <p class="mt-1 text-sm text-slate-600">{{ __('Xóa giao dịch, email ngân hàng đã đồng bộ, quy tắc, danh mục riêng và kết nối Gmail. Tài khoản đăng nhập vẫn được giữ để bạn bắt đầu lại.') }}</p>
    <button type="button" x-data x-on:click.prevent="$dispatch('open-modal', 'confirm-data-deletion')"
            class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-white px-4 text-sm font-semibold text-red-600 ring-1 ring-red-300 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">{{ __('Xóa dữ liệu') }}</button>

    <x-modal name="confirm-data-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteData" class="p-6">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Xóa toàn bộ dữ liệu?') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Giao dịch, email, quy tắc, danh mục riêng và kết nối Gmail sẽ bị xóa vĩnh viễn. Nhập mật khẩu đăng nhập để xác nhận.') }}</p>

            <div class="mt-6">
                <label for="delete-data-password" class="sr-only">{{ __('Password') }}</label>
                <x-text-input wire:model="password" id="delete-data-password" type="password" class="mt-1 block w-full" placeholder="{{ __('Password') }}" />
                @error('password') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                <x-danger-button>{{ __('Xóa dữ liệu') }}</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
