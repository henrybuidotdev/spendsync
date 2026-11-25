<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: true);
    }
}; ?>

@php
    // route, label, icon, route name patterns (server) and path prefixes (browser) that mark the item active
    $items = [
        ['transactions', __('Transactions'), 'list', ['transactions', 'transactions.*'], ['/transactions']],
        ['reports', __('Reports'), 'chart-no-axes-column', ['reports'], ['/reports']],
        ['settings', __('Settings'), 'settings', ['settings', 'profile', 'connect'], ['/settings', '/profile', '/connect']],
    ];

    $activeIndex = collect($items)->search(fn (array $item) => request()->routeIs(...$item[3]));
@endphp

<nav class="pointer-events-none fixed inset-x-0 bottom-0 z-20 mx-auto max-w-lg px-4 pb-[calc(env(safe-area-inset-bottom)+0.75rem)]" aria-label="{{ __('Main navigation') }}">
    {{--
        The nav is @persist-ed across wire:navigate. The highlight slides with element.animate() rather than a CSS
        transition: Livewire detaches and re-attaches persisted elements when the next page arrives (often within
        a few dozen ms), which cancels CSS transitions but not Web Animations. Back/forward and in-page links re-sync from the URL.
    --}}
    <div x-data="{
            active: @js($activeIndex),
            prefixes: @js(array_column($items, 4)),
            offset(index) {
                return `translateX(calc(${index} * (100% + 0.25rem)))`;
            },
            moveTo(index) {
                if (index === this.active) {
                    return;
                }

                const from = this.active;
                this.active = index;

                if (from === false || index === false || matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }

                this.$refs.highlight.animate(
                    [{ transform: this.offset(from) }, { transform: this.offset(index) }],
                    { duration: 450, easing: 'cubic-bezier(0.32, 0.72, 0, 1)' },
                );
            },
            syncWithUrl() {
                const index = this.prefixes.findIndex((paths) => paths.some((path) => location.pathname.startsWith(path)));
                this.moveTo(index === -1 ? false : index);
            },
         }"
         x-on:livewire:navigated.document="syncWithUrl"
         class="pointer-events-auto relative grid grid-cols-3 gap-1 rounded-full bg-white p-1 shadow">
        <span x-ref="highlight" aria-hidden="true" x-show="active !== false"
              class="absolute inset-y-1 left-1 w-[calc((100%-1rem)/3)] rounded-full bg-slate-950/10"
              style="transform: translateX(calc({{ (int) $activeIndex }} * (100% + 0.25rem)))"
              :style="{ transform: offset(active || 0) }"></span>

        @foreach($items as $index => [$route, $label, $icon])
            <a href="{{ route($route) }}" wire:navigate
               @if($route !== 'settings')
                   x-data="{ keepMonth() { const url = new URL($el.href); const month = new URLSearchParams(location.search).get('month'); month ? url.searchParams.set('month', month) : url.searchParams.delete('month'); $el.href = url; } }"
                   x-on:pointerdown="keepMonth" x-on:focus="keepMonth"
               @endif
               x-on:click="moveTo({{ $index }})"
               @if($index === $activeIndex) aria-current="page" @endif
               :aria-current="active === {{ $index }} ? 'page' : null"
               :class="{ 'text-slate-950': active === {{ $index }}, 'text-slate-500 hover:text-slate-800': active !== {{ $index }} }"
               class="relative flex min-h-14 flex-col items-center justify-center gap-0.5 rounded-full text-xs font-medium transition-colors duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 {{ $index === $activeIndex ? 'text-slate-950' : 'text-slate-500 hover:text-slate-800' }}">
                <x-icon :name="$icon" class="h-5 w-5" />
                {{ $label }}
            </a>
        @endforeach
    </div>
</nav>
