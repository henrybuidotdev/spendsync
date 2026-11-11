<x-app-layout>
    <div class="space-y-4">
        <a href="{{ route('settings') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700">‹ {{ __('Cài đặt') }}</a>

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <livewire:profile.update-password-form />
        </div>

        <div class="rounded-2xl border border-red-200 bg-white p-4 shadow-sm">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
