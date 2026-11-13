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
    <div class="mx-auto grid max-w-lg grid-cols-3">
        @foreach([
            ['transactions', __('Transactions'), 'list', ['transactions', 'transactions.*']],
            ['reports', __('Reports'), 'chart-no-axes-column', ['reports']],
            ['settings', __('Settings'), 'settings', ['settings', 'profile', 'connect']],
        ] as [$route, $label, $icon, $patterns])
            @php($active = request()->routeIs(...$patterns))
            <a href="{{ route($route) }}" wire:navigate
               @if($active) aria-current="page" @endif
               class="flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500 {{ $active ? 'text-emerald-700' : 'text-slate-500 hover:text-slate-800' }}">
                <x-icon :name="$icon" class="h-6 w-6" />
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
