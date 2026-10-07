<x-layouts.app :title="__('Confirm your password')" active="admin">

<main class="wrap auth-wrap">
    <div class="panel auth-card">
        <h1>{{ __('Confirm your password') }}</h1>
        <p class="hint">{{ __('This moves money, so please enter your password again.') }}</p>
        @if ($errors->any())
            <ul class="form-errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('password.confirm.store') }}" class="auth-form">
            @csrf
            <div class="field">
                <label for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
            </div>
            <button type="submit" class="btn accent big">{{ __('Confirm') }}</button>
        </form>
    </div>
</main>

</x-layouts.app>
