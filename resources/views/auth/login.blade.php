<x-layouts.app :title="__('Log in')" active="login">

<main class="wrap auth-wrap">
    <div class="panel auth-card">
        <h1>{{ __('Log in') }}</h1>
        <p class="hint">{{ __('Log in to sell cars and manage your profile.') }}</p>
        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <ul class="form-errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('login') }}" class="auth-form">
            @csrf
            <div class="field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" maxlength="120" required autofocus autocomplete="email" placeholder="name@example.com" value="{{ old('email') }}">
            </div>
            <div class="field">
                <label for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="{{ __('Your password') }}">
            </div>
            <label class="remember"><input type="checkbox" name="remember" value="1"> {{ __('Keep me logged in') }}</label>
            <button type="submit" class="btn accent big">{{ __('Log in') }}</button>
        </form>
        <p class="auth-switch"><a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a></p>
        <p class="auth-switch">{{ __('New here?') }} <a href="{{ route('register') }}">{{ __('Create a seller account') }}</a></p>
    </div>
</main>

</x-layouts.app>
