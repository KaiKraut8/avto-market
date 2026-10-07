<x-layouts.app :title="__('Withdraw to bank')" active="admin">

<main class="wrap checkout-page">
    <div class="panel withdraw-box">
        <h1>{{ __('Withdraw to bank') }}</h1>
        @if (session('error'))
            <div class="notice error">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <ul class="form-errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif
        @if ($testMode)
            <div class="notice test-notice">{{ __('Test mode: this only records a test withdrawal. No money moves.') }}</div>
        @endif

        @include('admin._balance')

        <form method="post" action="{{ route('admin.withdraw') }}">
            @csrf
            <div class="field">
                <label for="amount">{{ __('Amount (leave empty for everything available)') }}</label>
                <input type="text" id="amount" name="amount" inputmode="decimal" placeholder="{{ number_format($balance['available'], 2, ',', '.') }}" value="{{ old('amount') }}">
            </div>
            <label class="remember"><input type="checkbox" name="confirm" value="1"> {{ __('Send this money to the bank account above.') }}</label>
            @unless ($testMode)
                <p class="hint">{{ __('Mollie sends it on the next business day. A manual withdrawal switches Mollie\'s automatic payouts off; you can switch them back on in the Mollie dashboard.') }}</p>
            @endunless
            <div class="cancel-actions">
                <button type="submit" class="btn gold big" @disabled($balance['available'] < 1 || ! $balance['destination'])>{{ __('Withdraw') }}</button>
                <a class="btn ghost" href="{{ route('admin.dashboard') }}">{{ __('Back') }}</a>
            </div>
        </form>
    </div>
</main>

</x-layouts.app>
