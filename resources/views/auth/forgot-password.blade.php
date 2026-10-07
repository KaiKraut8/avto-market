<x-layouts.app :title="__('Forgot your password?')" active="login">

<main class="wrap auth-wrap">
    <div class="panel auth-card">
        <h1>{{ __('Forgot your password?') }}</h1>
        <p class="hint">{{ __('Enter the email of your account and we will send you a link to choose a new password.') }}</p>
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
        <form method="post" action="{{ route('password.email') }}" class="auth-form">
            @csrf
            <div class="field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" maxlength="120" required autofocus autocomplete="email" value="{{ old('email') }}">
            </div>
            <button type="submit" class="btn accent big">{{ __('Send reset link') }}</button>
        </form>
        <p class="auth-switch"><a href="{{ route('login') }}">{{ __('Back to log in') }}</a></p>
    </div>
</main>

</x-layouts.app>
