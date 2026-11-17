@php
    use App\Enums\MailAccountStatus;
    use App\Support\Format;
@endphp

<div class="space-y-5">
    <x-flash />

    @if($section === null)
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Settings') }}</h1>

        @php
            $rows = [
                ['gmail', 'mail', __('Gmail'), match ($account?->status) {
                    MailAccountStatus::Active => __('Active'),
                    MailAccountStatus::NeedsReconnect => __('Needs reconnect'),
                    MailAccountStatus::Disabled => __('Disconnected'),
                    null => __('Not connected'),
                }],
                ['categories', 'tag', __('Categories'), $categoryCount],
                ['rules', 'zap', __('Auto-categorize rules'), $ruleCount],
                ['appearance', 'contrast', __('Appearance & language'), app()->getLocale() === 'en' ? 'English' : 'Tiếng Việt'],
            ];
            $rowClass = 'flex min-h-14 w-full items-center gap-3 px-1 text-left hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500';
        @endphp

        <nav class="divide-y divide-slate-200 border-y border-slate-200" aria-label="{{ __('Settings') }}">
            @foreach($rows as [$key, $icon, $label, $detail])
                <a href="{{ route('settings', $key) }}" wire:navigate class="{{ $rowClass }}">
                    <x-icon :name="$icon" class="h-5 w-5 text-slate-500" />
                    <span class="min-w-0 flex-1 truncate font-medium">{{ $label }}</span>
                    <span @class(['shrink-0 text-sm', 'text-red-600' => $key === 'gmail' && $account?->status === MailAccountStatus::NeedsReconnect, 'text-slate-500' => ! ($key === 'gmail' && $account?->status === MailAccountStatus::NeedsReconnect)])>{{ $detail }}</span>
                    <x-icon name="chevron-right" class="h-4 w-4 text-slate-400" />
                </a>
            @endforeach
            <a href="{{ route('profile') }}" wire:navigate class="{{ $rowClass }}">
                <x-icon name="user" class="h-5 w-5 text-slate-500" />
                <span class="min-w-0 flex-1 truncate font-medium">{{ __('Profile & password') }}</span>
                <x-icon name="chevron-right" class="h-4 w-4 text-slate-400" />
            </a>
            <button type="button" wire:click="logout" class="{{ $rowClass }}">
                <x-icon name="log-out" class="h-5 w-5 text-slate-500" />
                <span class="min-w-0 flex-1 truncate font-medium">{{ __('Log out') }}</span>
            </button>
        </nav>

        <p class="text-center text-sm text-slate-500">
            <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Privacy') }}</a>
            <span aria-hidden="true">&nbsp;·&nbsp;</span>
            <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="inline-flex items-center underline hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Terms of use') }}</a>
        </p>
    @else
        <a href="{{ route('settings') }}" wire:navigate class="-ml-1 inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"><x-icon name="chevron-left" class="h-4 w-4" /> {{ __('Settings') }}</a>

        <div class="flex items-center justify-between gap-2">
            <h1 id="page-heading" class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
            @if($section === 'categories')
                {{-- Hidden, not removed, on Debt/Loan so the header doesn't shift when switching tabs. --}}
                <button type="button" x-on:click="$dispatch('open-modal', 'add-category')" @class(['-mr-2 inline-flex min-h-11 items-center gap-1 rounded-xl px-2 font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500', 'invisible' => $categoryType === 'debt_loan'])>+ {{ __('Add') }}</button>
            @endif
        </div>

        @switch($section)
            @case('gmail')
            <section aria-labelledby="page-heading">

                @if($account)
                    <p class="mt-2 break-all font-medium">{{ $account->email }}</p>
                    <p class="mt-1 text-sm">
                        @switch($account->status)
                            @case(MailAccountStatus::Active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800">{{ __('Active') }}</span>
                                @break
                            @case(MailAccountStatus::NeedsReconnect)
                                <span class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-800">{{ __('Needs reconnect') }}</span>
                                @break
                            @default
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 font-medium text-slate-700">{{ __('Disconnected') }}</span>
                        @endswitch
                    </p>
                    <p class="mt-2 flex flex-wrap items-center gap-x-2 text-sm text-slate-600">
                        <span>
                            {{ __('Last synced') }}:
                            {{ $account->last_run_at ? Format::local($account->last_run_at)->format('H:i d/m/Y').' ('.Format::local($account->last_run_at)->diffForHumans().')' : __('never') }}
                        </span>
                        @if($account->status === MailAccountStatus::Active)
                            @if($account->isSyncing())
                                <span wire:poll.3s role="status" class="inline-flex min-h-11 items-center font-medium text-slate-500">
                                    {{ __('Syncing') }}<span class="typing-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></span>
                                </span>
                            @elseif(! $account->syncedRecently())
                                <button type="button" wire:click="syncNow" wire:loading.attr="disabled"
                                        class="inline-flex min-h-11 items-center gap-1 rounded font-medium text-emerald-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-60">
                                    <x-icon name="refresh" class="h-4 w-4" /> {{ __('Sync now') }}
                                </button>
                            @endif
                        @endif
                    </p>
                    @if($account->last_error)
                        <p class="mt-1 text-sm text-red-600">
                            {{ __('Last error') }}:
                            {{ match ($account->last_error) {
                                'invalid_credentials' => __('The app password is invalid or was revoked.'),
                                'connection_failed' => __('Couldn\'t reach Gmail, will retry.'),
                                default => $account->last_error,
                            } }}
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('connect') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl bg-black px-4 text-sm font-medium text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Reconnect') }}</a>
                        @if($account->status !== MailAccountStatus::Disabled)
                            <button type="button" wire:click="disconnect" wire:confirm="{{ __('Disconnect Gmail? The app password is deleted; your transactions are kept.') }}"
                                    class="min-h-11 rounded-xl bg-white px-4 text-sm font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">{{ __('Disconnect') }}</button>
                        @endif
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-600">{{ __('Gmail is not connected.') }}</p>
                    <a href="{{ route('connect') }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-black px-4 text-sm font-medium text-white">{{ __('Connect Gmail') }}</a>
                @endif
            </section>

            <section class="border-t border-slate-200 pt-5" aria-labelledby="sync-heading">
                <h2 id="sync-heading" class="font-semibold">{{ __('How syncing works') }}</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">
                    <li>{{ __('Syncs automatically every hour. You can also tap "Sync now" above.') }}</li>
                    <li>{{ __('Only balance-change emails from supported banks are read. Other emails are never read, and nothing is marked as read.') }}</li>
                    <li>{{ __('The first sync looks back :days days. Each later sync starts :resync day(s) before the last successful sync, and emails already imported are skipped.', ['days' => config('services.mail_sync.first_sync_days'), 'resync' => config('services.mail_sync.resync_days')]) }}</li>
                    <li>{{ __('Deleting an email in Gmail does not delete its transaction in the app.') }}</li>
                </ul>
            </section>
                @break

            @case('categories')
            <section aria-labelledby="page-heading">

                <div class="tabs mt-3" role="group" aria-label="{{ __('Category type') }}">
                    @foreach(\App\Enums\CategoryType::cases() as $case)
                        <button type="button" wire:click="showCategoryType('{{ $case->value }}')" aria-pressed="{{ $categoryType === $case->value ? 'true' : 'false' }}" class="tab">{{ $case->label() }}</button>
                    @endforeach
                </div>

                <p class="mt-3 flex items-center gap-1.5 px-1 text-sm text-slate-500"><x-icon name="lock" class="h-3.5 w-3.5" /> {{ __('Default categories can\'t be edited or deleted.') }}</p>

                <ul class="mt-2 divide-y divide-slate-200">
                    @foreach($categories as $category)
                        <li @class(['flex min-h-14 items-center gap-2 py-1', 'pl-8' => $category->parent_id]) wire:key="category-{{ $category->id }}">
                            @if($editingId === $category->id)
                                <form wire:submit="renameCategory" class="flex min-w-0 flex-1 gap-2">
                                    <x-emoji-picker model="editingIcon" :label="__('Choose an icon')" />
                                    <label for="editing-name" class="sr-only">{{ __('Category name') }}</label>
                                    <input wire:model="editingName" id="editing-name" type="text" maxlength="50" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
                                    <button type="submit" class="min-h-11 rounded-xl bg-black px-3 text-sm font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">{{ __('Save') }}</button>
                                </form>
                            @else
                                <x-category-icon :category="$category" class="h-8 w-8 text-lg" />
                                <span class="min-w-0 flex-1 truncate">
                                    {{ $category->label }}
                                    @if($category->transactions_count > 0)
                                        <span class="text-xs text-slate-400">· {{ $category->transactions_count }}</span>
                                    @endif
                                </span>
                                @if($category->isSystem())
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center text-slate-400" title="{{ __('Default category, cannot be edited or deleted') }}"><x-icon name="lock" class="h-4 w-4" /><span class="sr-only">{{ __('Default category, cannot be edited or deleted') }}</span></span>
                                @else
                                <button type="button" wire:click="editCategory({{ $category->id }})" class="min-h-11 min-w-11 rounded-xl px-2 text-sm text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Edit :name', ['name' => $category->label]) }}"><x-icon name="pencil" class="mx-auto h-4 w-4" /></button>
                                <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Delete this category? Its transactions become uncategorized.') }}" class="min-h-11 min-w-11 rounded-xl px-2 text-sm text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="{{ __('Delete :name', ['name' => $category->label]) }}"><x-icon name="trash" class="mx-auto h-4 w-4" /></button>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
                @error('editingName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if($categoryType !== 'debt_loan')
                <x-modal name="add-category" maxWidth="md" focusable>
                    <form wire:submit="addCategory" x-on:category-added.window="$dispatch('close-modal', 'add-category')" class="space-y-4 p-4" aria-labelledby="add-category-title">
                        <div class="flex items-center justify-between gap-2">
                            <h2 id="add-category-title" class="text-lg font-semibold">{{ $categoryType === 'income' ? __('Add income category') : __('Add expense category') }}</h2>
                            <button type="button" x-on:click="$dispatch('close-modal', 'add-category')" class="-mr-2 flex min-h-11 min-w-11 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500" aria-label="{{ __('Close') }}"><x-icon name="x" class="h-5 w-5" /></button>
                        </div>

                        <div>
                            <label for="new-name" class="text-sm font-medium">{{ __('Category name') }}</label>
                            <div class="mt-1 flex gap-2">
                                <x-emoji-picker model="newIcon" :label="__('Choose an icon')" />
                                <input wire:model="newName" id="new-name" type="text" maxlength="50" class="min-h-11 min-w-0 flex-1 rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
                            </div>
                            @error('newName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="new-parent" class="text-sm font-medium">{{ __('Parent category') }}</label>
                            <select wire:model="newParentId" id="new-parent" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">{{ __('No parent (top level)') }}</option>
                                @foreach($categories->whereNull('parent_id') as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->icon }} {{ $parent->label }}</option>
                                @endforeach
                            </select>
                            @error('newParentId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit" class="min-h-11 w-full rounded-xl bg-black px-4 font-medium text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">{{ __('Add') }}</button>
                    </form>
                </x-modal>
                @endif
            </section>
                @break

            @case('rules')
            <section aria-labelledby="page-heading">
                @forelse($rules as $rule)
                    <div class="flex min-h-14 items-center gap-2 border-b border-slate-200 py-1 last:border-0" wire:key="rule-{{ $rule->id }}">
                        <p class="min-w-0 flex-1 text-sm">
                            <span class="text-slate-500">{{ match($rule->match_field) {
                                \App\Enums\MatchField::Account => __('Account number is'),
                                \App\Enums\MatchField::Counterparty => __('Counterparty is'),
                                \App\Enums\MatchField::Description => __('Description contains'),
                            } }}</span>
                            <span class="break-words font-medium">“{{ $rule->match_value }}”</span>
                            → {{ $rule->category->icon }} {{ $rule->category->label }}
                        </p>
                        <button type="button" wire:click="deleteRule({{ $rule->id }})" class="min-h-11 min-w-11 rounded-xl px-2 text-sm text-red-600 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="{{ __('Delete rule') }}"><x-icon name="trash" class="mx-auto h-4 w-4" /></button>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-slate-600">{{ __('No rules yet. Open a transaction, pick a category, then tick "Automatically set…" to create one.') }}</p>
                @endforelse
            </section>
                @break

            @case('appearance')
            <section aria-labelledby="language-heading">
                <h2 id="language-heading" class="font-semibold">{{ __('Language') }}</h2>
                <div class="segmented mt-2" role="group" aria-labelledby="language-heading">
                    @foreach(\App\Enums\Locale::cases() as $locale)
                        <button type="button" wire:click="setLocale('{{ $locale->value }}')" aria-pressed="{{ app()->getLocale() === $locale->value ? 'true' : 'false' }}" class="segment">{{ $locale->label() }}</button>
                    @endforeach
                </div>
            </section>

            <section class="border-t border-slate-200 pt-5" aria-labelledby="theme-heading"
                     x-data="{
                         theme: window.appTheme,
                         setTheme(theme) {
                             this.theme = window.appTheme = theme;

                             theme === 'system'
                                 ? delete document.documentElement.dataset.theme
                                 : document.documentElement.dataset.theme = theme;

                             $wire.setTheme(theme);
                         },
                     }">
                <h2 id="theme-heading" class="font-semibold">{{ __('Appearance') }}</h2>
                <div class="segmented mt-2" role="group" aria-labelledby="theme-heading">
                    @foreach(['light' => __('Light'), 'dark' => __('Dark'), 'system' => __('System')] as $value => $label)
                        <button type="button" x-on:click="setTheme('{{ $value }}')" x-bind:aria-pressed="theme === '{{ $value }}'" class="segment">{{ $label }}</button>
                    @endforeach
                </div>
            </section>
                @break
        @endswitch
    @endif
</div>
