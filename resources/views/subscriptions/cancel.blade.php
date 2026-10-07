<x-layouts.app :title="__('Cancel subscription')" active="account">

<main class="wrap checkout-page">
    <div class="panel cancel-box">
        <h1>{{ __('Cancel your :plan subscription?', ['plan' => $subscription->label()]) }}</h1>
        <p>{{ __('You keep everything until :date, the end of the period you already paid for. After that you will not be charged again and premium switches off.', ['date' => $subscription->current_period_end->format('Y-m-d')]) }}</p>
        <ul class="perks lose">
            @if ($subscription->kind === 'seller')
                <li>{{ __('Your cars lose their gold top placement.') }}</li>
                <li>{{ __('You can no longer start special deals; running ones finish as planned.') }}</li>
                <li>{{ __('Insights and interest alerts stop.') }}</li>
            @else
                <li>{{ __('Member prices on deals are no longer yours.') }}</li>
                <li>{{ __('Alerts for deals on cars like yours and your saved searches stop.') }}</li>
            @endif
        </ul>
        <p class="hint">{{ __('Changed your mind later? You can resume until :date and nothing is lost.', ['date' => $subscription->current_period_end->format('Y-m-d')]) }}</p>
        <div class="cancel-actions">
            <a class="btn gold" href="{{ route('account') }}">{{ __('Keep my subscription') }}</a>
            <form method="post" action="{{ route('subscriptions.cancel', $subscription) }}">
                @csrf
                <button type="submit" class="btn danger">{{ __('Yes, cancel the subscription') }}</button>
            </form>
        </div>
    </div>
</main>

</x-layouts.app>
