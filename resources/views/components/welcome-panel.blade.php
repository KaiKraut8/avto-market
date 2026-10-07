{{-- Drops down when a guest enters the site: sign up or log in, or "Skip for now" (hidden until the browser is closed).
     After a failed attempt it opens again on the same tab, with the errors. --}}
@php
    $failed = old('_auth_panel');
    $tab = $failed ?: 'register';
    $here = request()->getRequestUri();
    $mine = fn (string $panel) => $failed === $panel ? $errors->all() : [];
    $country = ($failed === 'register' ? old('country') : null) ?? config('countries')[0];
@endphp
<div class="welcome" data-welcome @if ($failed) data-instant @endif>
    <div class="welcome-backdrop" data-welcome-skip></div>
    <section class="welcome-panel" role="dialog" aria-modal="true" aria-labelledby="welcome-title">
        <div class="welcome-head">
            <a class="logo" href="{{ route('home') }}"><span class="mark"></span>KAI<em>Garage</em></a>
            <button type="button" class="welcome-skip" data-welcome-skip>{{ __('Skip for now') }} <span aria-hidden="true">&rarr;</span></button>
        </div>
        <h2 id="welcome-title">{{ __('Welcome to KAI Garage') }}</h2>
        <p class="hint">{{ __('Sign up to sell cars, keep your wishlist on every device and get alerts. Or just look around first.') }}</p>

        <div class="welcome-tabs" role="tablist">
            <button type="button" role="tab" id="wt-register" aria-controls="wp-register" aria-selected="{{ $tab === 'register' ? 'true' : 'false' }}" data-welcome-tab="register">{{ __('Sign up') }}</button>
            <button type="button" role="tab" id="wt-login" aria-controls="wp-login" aria-selected="{{ $tab === 'login' ? 'true' : 'false' }}" data-welcome-tab="login">{{ __('Log in') }}</button>
        </div>

        <form method="post" action="{{ route('register') }}" class="auth-form welcome-form" id="wp-register" role="tabpanel" aria-labelledby="wt-register" @if ($tab !== 'register') hidden @endif>
            @csrf
            <input type="hidden" name="_auth_panel" value="register">
            <input type="hidden" name="return_to" value="{{ $here }}">
            @if ($mine('register'))
                <ul class="form-errors">@foreach ($mine('register') as $e)<li>{{ $e }}</li>@endforeach</ul>
            @endif
            <div class="field-row">
                <div class="field">
                    <label for="wr-name">{{ __('Full name') }}</label>
                    <input type="text" id="wr-name" name="name" maxlength="60" required autocomplete="name" placeholder="{{ __('e.g. Janez Novak') }}" value="{{ $failed === 'register' ? old('name') : '' }}">
                </div>
                <div class="field">
                    <label for="wr-phone">{{ __('Phone') }}</label>
                    <input type="tel" id="wr-phone" name="phone" maxlength="25" required autocomplete="tel" placeholder="+386 40 123 456" value="{{ $failed === 'register' ? old('phone') : '' }}">
                </div>
            </div>
            <div class="field">
                <label for="wr-email">{{ __('Email') }}</label>
                <input type="email" id="wr-email" name="email" maxlength="120" required autocomplete="email" placeholder="name@example.com" value="{{ $failed === 'register' ? old('email') : '' }}">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="wr-password">{{ __('Password') }}</label>
                    <input type="password" id="wr-password" name="password" minlength="8" required autocomplete="new-password" placeholder="{{ __('At least 8 characters') }}">
                </div>
                <div class="field">
                    <label for="wr-password2">{{ __('Repeat password') }}</label>
                    <input type="password" id="wr-password2" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="wr-location">{{ __('Location') }}</label>
                    <input type="text" id="wr-location" name="location" maxlength="80" autocomplete="address-level2" placeholder="{{ __('e.g. Ljubljana') }}" value="{{ $failed === 'register' ? old('location') : '' }}">
                </div>
                <div class="field">
                    <label for="wr-country">{{ __('Country') }}</label>
                    <select id="wr-country" name="country">
                        @foreach (config('countries') as $c)
                            <option value="{{ $c }}" @selected($country === $c)>{{ __($c) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="hint">{!! __('How we use your details is explained in our :link.', ['link' => '<a href="'.route('privacy').'">'.e(__('Privacy policy')).'</a>']) !!}</p>
            <button type="submit" class="btn accent big">{{ __('Create account') }}</button>
        </form>

        <form method="post" action="{{ route('login') }}" class="auth-form welcome-form" id="wp-login" role="tabpanel" aria-labelledby="wt-login" @if ($tab !== 'login') hidden @endif>
            @csrf
            <input type="hidden" name="_auth_panel" value="login">
            <input type="hidden" name="return_to" value="{{ $here }}">
            @if ($mine('login'))
                <ul class="form-errors">@foreach ($mine('login') as $e)<li>{{ $e }}</li>@endforeach</ul>
            @endif
            <div class="field">
                <label for="wl-email">{{ __('Email') }}</label>
                <input type="email" id="wl-email" name="email" maxlength="120" required autocomplete="email" placeholder="name@example.com" value="{{ $failed === 'login' ? old('email') : '' }}">
            </div>
            <div class="field">
                <label for="wl-password">{{ __('Password') }}</label>
                <input type="password" id="wl-password" name="password" required autocomplete="current-password" placeholder="{{ __('Your password') }}">
            </div>
            <div class="welcome-row">
                <label class="remember"><input type="checkbox" name="remember" value="1"> {{ __('Keep me logged in') }}</label>
                <a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            </div>
            <button type="submit" class="btn accent big">{{ __('Log in') }}</button>
        </form>

        <button type="button" class="welcome-skip-big" data-welcome-skip>{{ __('Skip for now and look around') }}</button>
    </section>
</div>
