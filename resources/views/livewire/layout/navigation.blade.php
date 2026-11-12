<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]" aria-label="{{ __('Main navigation') }}">
    <div class="mx-auto grid max-w-md grid-cols-3">
        @foreach([
            ['transactions', __('Transactions'), 'M4 6h16M4 12h16M4 18h10', ['transactions', 'transactions.*']],
            ['reports', __('Reports'), 'M5 20V10m7 10V4m7 16v-7', ['reports']],
            ['settings', __('Settings'), 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-1.5.9 1.6-1.9 3.2-1.8-.3a7.5 7.5 0 0 1-1.6.9L14.5 21h-5l-.5-2.1a7.5 7.5 0 0 1-1.6-.9l-1.8.3-1.9-3.2.9-1.6a7.6 7.6 0 0 1 0-1.8l-.9-1.6 1.9-3.2 1.8.3a7.5 7.5 0 0 1 1.6-.9L9.5 3h5l.5 2.1c.6.2 1.1.5 1.6.9l1.8-.3 1.9 3.2-.9 1.6a7.6 7.6 0 0 1 0 1.8Z', ['settings', 'profile', 'connect']],
        ] as [$route, $label, $icon, $patterns])
            @php($active = request()->routeIs(...$patterns))
            <a href="{{ route($route) }}" wire:navigate
               @if($active) aria-current="page" @endif
               class="flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500 {{ $active ? 'text-emerald-700' : 'text-slate-500 hover:text-slate-800' }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
