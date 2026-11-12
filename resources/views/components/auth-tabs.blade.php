<nav class="mb-6 grid grid-cols-2 gap-1 rounded-full bg-slate-100 p-1" aria-label="{{ __('Account') }}">
    @foreach(['login' => __('Log in'), 'register' => __('Register')] as $route => $label)
        <a href="{{ route($route) }}" wire:navigate
           @if(request()->routeIs($route)) aria-current="page" @endif
           @class([
               'flex min-h-11 items-center justify-center rounded-full text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950',
               'bg-white text-slate-950 shadow-sm' => request()->routeIs($route),
               'text-slate-500 hover:text-slate-900' => ! request()->routeIs($route),
           ])>{{ $label }}</a>
    @endforeach
</nav>
