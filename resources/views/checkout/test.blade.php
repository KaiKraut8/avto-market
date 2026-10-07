<x-layouts.app :title="__('Test payment')" active="premium">

<main class="wrap checkout-page">
    <div class="panel test-pay">
        <p class="eyebrow amber">{{ __('Test mode') }}</p>
        <h1>{{ __('Test payment') }}</h1>
        <p>{{ __('No payment provider is connected yet (add MOLLIE_KEY to .env). Choose what should happen to this payment; no money is taken.') }}</p>
        <table class="specs">
            <tr><th>{{ __('For') }}</th><td>{{ $payment->description }}</td></tr>
            <tr><th>{{ __('Amount') }}</th><td>@eur($payment->amount)</td></tr>
            <tr><th>{{ __('Method') }}</th><td>{{ \App\Models\Payment::methodName($payment->method) }}</td></tr>
        </table>
        <form method="post" action="{{ route('checkout.test.complete', $payment) }}" class="test-pay-actions">
            @csrf
            <button type="submit" name="outcome" value="paid" class="btn gold">{{ __('Pay (test)') }}</button>
            <button type="submit" name="outcome" value="failed" class="btn danger">{{ __('Payment fails') }}</button>
            <button type="submit" name="outcome" value="canceled" class="btn ghost">{{ __('Cancel') }}</button>
        </form>
    </div>
</main>

</x-layouts.app>
