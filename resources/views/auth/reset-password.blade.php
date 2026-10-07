<x-layouts.app :title="__('Choose a new password')" active="login">

<main class="wrap auth-wrap">
    <div class="panel auth-card">
        <h1>{{ __('Choose a new password') }}</h1>
        @if ($errors->any())
            <ul class="form-errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('password.update') }}" class="auth-form">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div class="field">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" maxlength="120" required autocomplete="email" value="{{ old('email', $request->email) }}">
            </div>
            <div class="field">
                <label for="password">{{ __('New password') }}</label>
                <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
            </div>
            <div class="field">
                <label for="password_confirmation">{{ __('Repeat new password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn accent big">{{ __('Save new password') }}</button>
        </form>
    </div>
</main>

</x-layouts.app>
