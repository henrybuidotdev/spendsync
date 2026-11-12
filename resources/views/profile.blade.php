<x-app-layout>
    <div class="space-y-4">
        <a href="{{ route('settings') }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700">‹ {{ __('Settings') }}</a>

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <livewire:profile.update-password-form />
        </div>

        <section class="divide-y divide-red-100 rounded-2xl border border-red-200 bg-white p-4 shadow-sm" aria-labelledby="danger-heading">
            <h2 id="danger-heading" class="pb-3 font-semibold text-red-700">⚠️ {{ __('Danger Zone') }}</h2>

            <div class="py-4">
                <livewire:profile.delete-data-form />
            </div>

            <div class="pt-4">
                <livewire:profile.delete-user-form />
            </div>
        </section>
    </div>
</x-app-layout>
