@props(['title' => null, 'active' => null, 'backToCars' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}KAI Garage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;700;800&family=Inter:wght@400;500;600&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        // labels the scripts show, in the current language
        $i18n = [
            'loading' => __('Loading…'),
            'views_failed' => __('Could not load views.'),
            'preparing_photos' => __('Preparing photos...'),
            'copied' => __('Copied!'),
        ];
    @endphp
    <script>window.KAI = { i18n: {{ Js::from($i18n) }} };</script>
</head>
<body>

<header class="topbar">
    <div class="inner">
        <a class="logo" href="{{ route('home') }}"><span class="mark"></span>KAI<em>Garage</em></a>
        <nav class="nav">
            <a href="{{ route('home') }}" @class(['active' => $active === 'home'])>{{ __('Home') }}</a>
            <a href="{{ route('cars.index') }}" @class(['active' => $active === 'cars'])>{{ __('All cars') }}</a>
            <a href="{{ route('most-watched') }}" @class(['active' => $active === 'most-watched'])>{{ __('Most watched') }}</a>
            <a href="{{ route('wishlist.index') }}" @class(['active' => $active === 'wishlist'])>{{ __('Wishlist') }} <span class="nav-count" data-wish-count @if (! $wishCount) hidden @endif>{{ $wishCount }}</span></a>
            <a href="{{ route('premium.index') }}" @class(['active' => $active === 'premium'])><span class="nav-crown" aria-hidden="true">&#9813;</span> {{ __('Premium') }}</a>
            <a class="btn accent" href="{{ route('cars.create') }}">+ {{ __('Add car') }}</a>
            @auth
                <a @class(['account-chip', 'active' => $active === 'account']) href="{{ route('account') }}" title="{{ __('Your profile') }}">
                    <span class="chip-avatar">{{ auth()->user()->initial() }}</span>
                    {{ auth()->user()->firstName() }}@if (auth()->user()->hasPremium()) <span class="nav-crown" title="{{ __('Premium seller') }}">&#9813;</span>@endif
                </a>
            @else
                <a href="{{ route('login') }}" @class(['active' => $active === 'login'])>{{ __('Log in') }}</a>
                <a class="btn ghost" href="{{ route('register') }}">{{ __('Sign up') }}</a>
            @endauth

            {{-- language picker, far right --}}
            <details class="lang-menu">
                <summary aria-label="{{ __('Language') }}" title="{{ __('Language') }}">
                    <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><circle cx="12" cy="12" r="9.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M2.5 12h19M12 2.5c2.6 2.8 3.9 6 3.9 9.5s-1.3 6.7-3.9 9.5c-2.6-2.8-3.9-6-3.9-9.5s1.3-6.7 3.9-9.5Z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                    <span>{{ strtoupper(app()->getLocale()) }}</span>
                </summary>
                <ul>
                    @foreach (config('locales') as $code => $language)
                        <li>
                            <a href="{{ route('language', $code) }}" lang="{{ $code }}" @class(['current' => app()->getLocale() === $code]) @if (app()->getLocale() === $code) aria-current="true" @endif>
                                <b>{{ strtoupper($code) }}</b> {{ $language }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        </nav>
    </div>
</header>

@if ($active !== 'home')
    <nav class="back-menu" aria-label="{{ __('Go back') }}">
        <a class="back-fab" href="{{ route('home') }}" aria-label="{{ __('Back to the garage') }}">
            <span class="arrow" aria-hidden="true">&larr;</span>
            <span class="label">{{ __('Back to the garage') }}</span>
        </a>
        @if ($backToCars)
            <a class="back-sub" href="{{ route('cars.index') }}">
                <span class="arrow" aria-hidden="true">&larr;</span>
                <span class="label">{{ __('Back to all cars') }}</span>
            </a>
        @endif
    </nav>
@endif

{{ $slot }}

<footer class="footer">
    <div class="inner footer-grid">
        <div>
            <a class="logo" href="{{ route('home') }}"><span class="mark"></span>KAI<em>Garage</em></a>
            <p class="footer-tag">{{ __(config('company.tagline')) }}</p>
        </div>
        <div>
            <h3>{{ __('Contact') }}</h3>
            <ul>
                <li><span>{{ __('Email') }}</span><a href="{{ company_mailto() }}">{{ config('company.email') }}</a></li>
                <li><span>{{ __('Phone') }}</span><a href="{{ route('contact') }}">{{ config('company.phone') }}</a></li>
            </ul>
        </div>
        <div>
            <h3>{{ __('Visit us') }}</h3>
            <ul>
                <li><span>{{ __('Address') }}</span><a href="{{ config('company.map_url') }}" target="_blank" rel="noopener">{{ config('company.address') }}</a></li>
                <li><span>{{ __('Hours') }}</span>{{ opening_hours_summary() }}</li>
            </ul>
        </div>
    </div>
    <div class="inner footer-bottom">&copy; {{ date('Y') }} {{ config('company.name') }}. {{ __('All rights reserved.') }} · <a href="{{ route('contact') }}">{{ __('Contact us') }}</a></div>
</footer>

</body>
</html>
