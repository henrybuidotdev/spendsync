<x-app-layout>
    <div class="space-y-5">
        <a href="{{ route('settings') }}" wire:navigate class="-ml-1 inline-flex min-h-11 items-center gap-1 font-medium text-emerald-700"><x-icon name="chevron-left" class="h-4 w-4" /> {{ __('Settings') }}</a>

        <div>
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="border-t border-slate-200 pt-5">
            <livewire:profile.update-password-form />
        </div>

        <section class="divide-y divide-red-200 border-t border-red-300 pt-5" aria-labelledby="danger-heading">
            <h2 id="danger-heading" class="flex items-center gap-2 pb-3 font-semibold text-red-700"><x-icon name="alert" class="h-4 w-4" /> {{ __('Danger Zone') }}</h2>

            <div class="py-4">
                <livewire:profile.delete-data-form />
            </div>

            <div class="pt-4">
                <livewire:profile.delete-user-form />
            </div>
        </section>
    </div>
</x-app-layout>
