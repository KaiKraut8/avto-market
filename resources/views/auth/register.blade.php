<x-layouts.app :title="__('Sign up')" active="register">

<main class="wrap auth-wrap">
    <div class="panel auth-card">
        <h1>{{ __('Create your seller account') }}</h1>
        <p class="hint">{{ __('One profile for all your cars. Buyers reach you through these contact details.') }}</p>
        @if ($errors->any())
            <ul class="form-errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('register') }}" class="auth-form">
            @csrf
            <div class="field">
                <label for="name">{{ __('Full name') }}</label>
                <input type="text" id="name" name="name" maxlength="60" required autocomplete="name" placeholder="{{ __('e.g. Janez Novak') }}" value="{{ old('name') }}">
            </div>
            <div class="field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" maxlength="120" required autocomplete="email" placeholder="name@example.com" value="{{ old('email') }}">
            </div>
            <div class="field">
                <label for="phone">{{ __('Phone') }}</label>
                <input type="tel" id="phone" name="phone" maxlength="25" required autocomplete="tel" placeholder="+386 40 123 456" value="{{ old('phone') }}">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="password">{{ __('Password') }}</label>
                    <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" placeholder="{{ __('At least 8 characters') }}">
                </div>
                <div class="field">
                    <label for="password_confirmation">{{ __('Repeat password') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="location">{{ __('Location') }}</label>
                    <input type="text" id="location" name="location" maxlength="80" autocomplete="address-level2" placeholder="{{ __('e.g. Ljubljana') }}" value="{{ old('location') }}">
                </div>
                <div class="field">
                    <label for="country">{{ __('Country') }}</label>
                    <select id="country" name="country">
                        @foreach (config('countries') as $c)
                            <option value="{{ $c }}" @selected(old('country', config('countries')[0]) === $c)>{{ __($c) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="btn accent big">{{ __('Create account') }}</button>
        </form>
        <p class="auth-switch">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a></p>
    </div>
</main>

</x-layouts.app>
