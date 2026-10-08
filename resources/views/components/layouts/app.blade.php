@props(['title' => null, 'active' => null, 'backToCars' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Vozi</title>
    <meta name="description" content="{{ __(config('company.tagline')) }}">
    {{-- icons: Google shows the 48 px (or larger) one next to the site in search results --}}
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-48x48.png" type="image/png" sizes="48x48">
    <link rel="icon" href="/favicon-96x96.png" type="image/png" sizes="96x96">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0e1116">
    {{-- link previews in chats and social networks --}}
    <meta property="og:site_name" content="Vozi">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ? $title.' · ' : '' }}Vozi">
    <meta property="og:description" content="{{ __(config('company.tagline')) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    @if (request()->routeIs('home'))
        {{-- tells Google the site's name and logo --}}
        @php
            $site = [
                '@'.'context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => 'WebSite', 'name' => 'Vozi', 'url' => url('/')],
                    ['@type' => 'Organization', 'name' => config('company.name'), 'url' => url('/'), 'logo' => asset('icon-512.png'),
                        'email' => config('company.email'), 'telephone' => config('company.phone')],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($site, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif
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
        <a class="logo" href="{{ route('home') }}"><span class="mark"></span><span>Vo<em>zi</em></span></a>
        <nav class="nav">
            <a href="{{ route('home') }}" @class(['active' => $active === 'home'])>{{ __('Home') }}</a>
            <a href="{{ route('cars.index') }}" @class(['active' => $active === 'cars'])>{{ __('All cars') }}</a>
            <a href="{{ route('deals.index') }}" @class(['active' => $active === 'deals'])>{{ __('Deals') }}</a>
            <a href="{{ route('most-watched') }}" @class(['active' => $active === 'most-watched'])>{{ __('Most watched') }}</a>
            <a href="{{ route('wishlist.index') }}" @class(['active' => $active === 'wishlist'])>{{ __('Wishlist') }} <span class="nav-count" data-wish-count @if (! $wishCount) hidden @endif>{{ $wishCount }}</span></a>
            <a href="{{ route('premium.index') }}" @class(['active' => $active === 'premium'])><span class="nav-crown" aria-hidden="true">&#9813;</span> {{ __('Premium') }}</a>
            <a class="btn accent" href="{{ route('cars.create') }}">+ {{ __('Add car') }}</a>
            @can('admin')
                <a href="{{ route('admin.dashboard') }}" @class(['nav-admin', 'active' => $active === 'admin'])>{{ __('Admin') }}</a>
            @endcan
            @auth
                <a @class(['nav-bell', 'active' => $active === 'notifications']) href="{{ route('notifications.index') }}" title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}">
                    <x-icon name="bell" :size="19" />
                    @if ($unreadAlerts)
                        <span class="nav-count">{{ $unreadAlerts }}</span>
                    @endif
                </a>
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
            <a class="logo" href="{{ route('home') }}"><span class="mark"></span><span>Vo<em>zi</em></span></a>
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
    <div class="inner footer-bottom">&copy; {{ date('Y') }} {{ config('company.name') }}. {{ __('All rights reserved.') }} · <a href="{{ route('contact') }}">{{ __('Contact us') }}</a> · <a href="{{ route('how-buying') }}">{{ __('How buying works') }}</a> · <a href="{{ route('privacy') }}">{{ __('Privacy policy') }}</a> · <a href="{{ route('cookies') }}">{{ __('Cookies') }}</a></div>
</footer>

{{-- sign up / log in panel for guests entering the site, unless skipped in this browser session --}}
@guest
    @if ((! request()->cookie('kai_auth_skipped') || old('_auth_panel')) && ! request()->routeIs('login', 'register', 'password.*'))
        <x-welcome-panel />
    @endif
@endguest

<x-assistant />

{{-- cookie notice: part of the page itself, so it shows at once; hidden for good after "OK" --}}
@unless (request()->cookie('kai_cookies_seen'))
    <div class="cookie-notice" data-cookie-notice role="region" aria-label="{{ __('Cookies') }}">
        <x-icon name="cookie" :size="26" class="cookie-notice-icon" />
        <p>{{ __('Vozi uses only necessary cookies: to keep you logged in, protect forms and remember your wishlist. No tracking and no ads.') }}
            <a href="{{ route('cookies') }}">{{ __('Cookie policy') }}</a></p>
        <button type="button" class="btn accent" data-cookie-ok>{{ __('OK, got it') }}</button>
    </div>
@endunless

</body>
</html>
