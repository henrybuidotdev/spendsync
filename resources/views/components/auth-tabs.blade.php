<nav class="tabs mb-6" aria-label="{{ __('Account') }}">
    @foreach(['login' => __('Log in'), 'register' => __('Register')] as $route => $label)
        <a href="{{ route($route) }}" wire:navigate @if(request()->routeIs($route)) aria-current="page" @endif class="tab">{{ $label }}</a>
    @endforeach
</nav>
