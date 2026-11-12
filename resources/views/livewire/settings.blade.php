@php
    use App\Enums\MailAccountStatus;
    use App\Support\Format;
@endphp

<div class="space-y-5">
    <x-flash />

    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Cài đặt') }}</h1>

    <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="account-heading">
        <h2 id="account-heading" class="font-semibold">{{ __('Tài khoản Gmail') }}</h2>

        @if($account)
            <p class="mt-2 break-all font-medium">{{ $account->email }}</p>
            <p class="mt-1 text-sm">
                @switch($account->status)
                    @case(MailAccountStatus::Active)
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800">{{ __('Đang hoạt động') }}</span>
                        @break
                    @case(MailAccountStatus::NeedsReconnect)
                        <span class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800">{{ __('Cần kết nối lại') }}</span>
                        @break
                    @default
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 font-medium text-slate-700">{{ __('Đã ngắt kết nối') }}</span>
                @endswitch
            </p>
            <p class="mt-2 text-sm text-slate-600">
                {{ __('Đồng bộ lần cuối') }}:
                {{ $account->last_synced_at ? Format::local($account->last_synced_at)->format('H:i d/m/Y').' ('.Format::local($account->last_synced_at)->diffForHumans().')' : __('chưa có') }}
            </p>
            @if($account->last_error)
                <p class="mt-1 text-sm text-red-600">
                    {{ __('Lỗi gần nhất') }}:
                    {{ match ($account->last_error) {
                        'invalid_credentials' => __('Mật khẩu ứng dụng không hợp lệ hoặc đã bị thu hồi.'),
                        'connection_failed' => __('Không kết nối được tới Gmail, sẽ thử lại.'),
                        default => $account->last_error,
                    } }}
                </p>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('connect') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl bg-black px-4 text-sm font-medium text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Kết nối lại') }}</a>
                @if($account->status !== MailAccountStatus::Disabled)
                    <button type="button" wire:click="disconnect" wire:confirm="{{ __('Ngắt kết nối Gmail? Mật khẩu ứng dụng sẽ bị xóa, giao dịch cũ vẫn được giữ.') }}"
                            class="min-h-11 rounded-xl bg-white px-4 text-sm font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">{{ __('Ngắt kết nối') }}</button>
                @endif
            </div>
        @else
            <p class="mt-2 text-sm text-slate-600">{{ __('Chưa kết nối Gmail.') }}</p>
            <a href="{{ route('connect') }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-black px-4 text-sm font-medium text-white">{{ __('Kết nối Gmail') }}</a>
        @endif
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="categories-heading">
        <h2 id="categories-heading" class="font-semibold">{{ __('Danh mục') }}</h2>

        <div class="mt-3 grid grid-cols-2 gap-1 rounded-full bg-slate-100 p-1" role="group" aria-label="{{ __('Loại danh mục') }}">
            @foreach(['expense' => __('Khoản chi'), 'income' => __('Khoản thu')] as $type => $label)
                <button type="button" wire:click="showCategoryType('{{ $type }}')" aria-pressed="{{ $categoryType === $type ? 'true' : 'false' }}"
                        @class(['min-h-11 rounded-full text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-white text-slate-900 shadow-sm' => $categoryType === $type, 'text-slate-600 hover:text-slate-900' => $categoryType !== $type])>{{ $label }}</button>
            @endforeach
        </div>

        <ul class="mt-2 divide-y divide-slate-100">
            @foreach($categories as $category)
                <li class="flex min-h-14 items-center gap-2 py-1" wire:key="category-{{ $category->id }}">
                    @if($editingId === $category->id)
                        <form wire:submit="renameCategory" class="flex min-w-0 flex-1 gap-2">
                            <x-emoji-picker model="editingIcon" :label="__('Chọn biểu tượng')" />
                            <label for="editing-name" class="sr-only">{{ __('Tên danh mục') }}</label>
                            <input wire:model="editingName" id="editing-name" type="text" maxlength="50" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
                            <button type="submit" class="min-h-11 rounded-xl bg-black px-3 text-sm font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Lưu') }}</button>
                        </form>
                    @else
                        <x-category-icon :category="$category" class="h-8 w-8 text-lg" />
                        <span class="min-w-0 flex-1 truncate">
                            {{ $category->name }}
                            <span class="text-xs text-slate-400">· {{ $category->transactions_count }}</span>
                        </span>
                        @if($category->isSystem())
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500" title="{{ __('Danh mục mặc định, không thể sửa hoặc xóa') }}">🔒 {{ __('Mặc định') }}</span>
                        @else
                        <button type="button" wire:click="editCategory({{ $category->id }})" class="min-h-11 min-w-11 rounded-xl px-2 text-sm text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Sửa :name', ['name' => $category->name]) }}">✏️</button>
                        <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Xóa danh mục này? Các giao dịch của nó sẽ thành chưa phân loại.') }}" class="min-h-11 min-w-11 rounded-xl px-2 text-sm text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="{{ __('Xóa :name', ['name' => $category->name]) }}">🗑️</button>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
        @error('editingName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <form wire:submit="addCategory" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
            <p class="text-sm font-medium">{{ $categoryType === 'income' ? __('Thêm khoản thu') : __('Thêm khoản chi') }}</p>
            <div class="flex gap-2">
                <x-emoji-picker model="newIcon" :label="__('Chọn biểu tượng')" />
                <label for="new-name" class="sr-only">{{ __('Tên danh mục') }}</label>
                <input wire:model="newName" id="new-name" type="text" maxlength="50" placeholder="{{ __('Tên danh mục') }}" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
                <button type="submit" class="min-h-11 shrink-0 rounded-xl bg-black px-4 text-sm font-medium text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Thêm') }}</button>
            </div>
            @error('newName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </form>
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="rules-heading">
        <h2 id="rules-heading" class="font-semibold">{{ __('Quy tắc tự phân loại') }}</h2>
        @forelse($rules as $rule)
            <div class="flex min-h-14 items-center gap-2 border-b border-slate-100 py-1 last:border-0" wire:key="rule-{{ $rule->id }}">
                <p class="min-w-0 flex-1 text-sm">
                    <span class="text-slate-500">{{ $rule->match_field->value === 'counterparty' ? __('Người nhận/gửi là') : __('Nội dung có') }}</span>
                    <span class="break-words font-medium">“{{ $rule->match_value }}”</span>
                    → {{ $rule->category->icon }} {{ $rule->category->name }}
                </p>
                <button type="button" wire:click="deleteRule({{ $rule->id }})" class="min-h-11 min-w-11 rounded-xl px-2 text-sm text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="{{ __('Xóa quy tắc') }}">🗑️</button>
            </div>
        @empty
            <p class="mt-2 text-sm text-slate-600">{{ __('Chưa có quy tắc nào. Khi bạn chọn danh mục cho một giao dịch, ứng dụng sẽ ghi nhớ cho các lần sau.') }}</p>
        @endforelse
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="language-heading">
        <h2 id="language-heading" class="font-semibold">{{ __('Ngôn ngữ') }}</h2>
        <div class="mt-2 grid grid-cols-2 gap-1 rounded-full bg-slate-100 p-1" role="group">
            @foreach(['vi' => 'Tiếng Việt', 'en' => 'English'] as $code => $label)
                <button type="button" wire:click="setLocale('{{ $code }}')" aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}"
                        @class(['min-h-11 rounded-full text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'bg-white text-slate-900 shadow-sm' => app()->getLocale() === $code, 'text-slate-600 hover:text-slate-900' => app()->getLocale() !== $code])>{{ $label }}</button>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl bg-white p-4 shadow-sm" aria-labelledby="theme-heading"
             x-data="{
                 theme: document.documentElement.dataset.theme ?? 'system',
                 setTheme(theme) {
                     this.theme = theme;

                     try {
                         theme === 'system' ? localStorage.removeItem('theme') : localStorage.setItem('theme', theme);
                     } catch (e) {}

                     theme === 'system'
                         ? delete document.documentElement.dataset.theme
                         : document.documentElement.dataset.theme = theme;
                 },
             }">
        <h2 id="theme-heading" class="font-semibold">{{ __('Giao diện') }}</h2>
        <div class="mt-2 grid grid-cols-3 gap-1 rounded-full bg-slate-100 p-1" role="group">
            @foreach(['light' => __('Sáng'), 'dark' => __('Tối'), 'system' => __('Theo hệ thống')] as $value => $label)
                <button type="button" x-on:click="setTheme('{{ $value }}')" x-bind:aria-pressed="theme === '{{ $value }}'"
                        class="min-h-11 rounded-full text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                        x-bind:class="theme === '{{ $value }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'">{{ $label }}</button>
            @endforeach
        </div>
    </section>

    <section class="space-y-1 rounded-2xl bg-white p-2 shadow-sm">
        <a href="{{ route('profile') }}" wire:navigate class="flex min-h-11 items-center rounded-xl px-2 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">👤 {{ __('Hồ sơ & mật khẩu') }}</a>
        <a href="{{ route('privacy') }}" wire:navigate class="flex min-h-11 items-center rounded-xl px-2 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">🛡️ {{ __('Quyền riêng tư') }}</a>
        <button type="button" wire:click="logout" class="flex min-h-11 w-full items-center rounded-xl px-2 text-left hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">🚪 {{ __('Đăng xuất') }}</button>
    </section>

    <section class="rounded-2xl border border-red-200 bg-white p-4 shadow-sm" aria-labelledby="danger-heading">
        <h2 id="danger-heading" class="font-semibold text-red-700">⚠️ {{ __('Vùng nguy hiểm') }}</h2>

        <div class="mt-3 border-b border-red-100 pb-4">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Xóa dữ liệu') }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ __('Xóa giao dịch, email ngân hàng đã đồng bộ, quy tắc, danh mục riêng và kết nối Gmail. Tài khoản đăng nhập vẫn được giữ để bạn bắt đầu lại.') }}</p>
            <button type="button" x-data x-on:click.prevent="$dispatch('open-modal', 'confirm-data-deletion')"
                    class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-white px-4 text-sm font-semibold text-red-600 ring-1 ring-red-300 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">{{ __('Xóa dữ liệu') }}</button>

            <x-modal name="confirm-data-deletion" :show="$errors->has('deleteDataPassword')" focusable>
                <form wire:submit="deleteData" class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Xóa toàn bộ dữ liệu?') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('Giao dịch, email, quy tắc, danh mục riêng và kết nối Gmail sẽ bị xóa vĩnh viễn. Nhập mật khẩu đăng nhập để xác nhận.') }}</p>

                    <div class="mt-6">
                        <label for="delete-data-password" class="sr-only">{{ __('Password') }}</label>
                        <x-text-input wire:model="deleteDataPassword" id="delete-data-password" type="password" class="mt-1 block w-full" placeholder="{{ __('Password') }}" />
                        @error('deleteDataPassword') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button>{{ __('Xóa dữ liệu') }}</x-danger-button>
                    </div>
                </form>
            </x-modal>
        </div>

        <div class="pt-4">
            <livewire:profile.delete-user-form />
        </div>
    </section>
</div>
