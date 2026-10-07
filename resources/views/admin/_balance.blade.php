{{-- balance at the payment provider and where it is paid out --}}
@php
    $frequency = match ($balance['frequency'] ?? null) {
        null => null,
        'daily' => __('every business day'),
        'twice-a-week' => __('twice a week'),
        'twice-a-month' => __('twice a month'),
        'monthly' => __('once a month'),
        'never' => __('only when you withdraw'),
        default => __('every week'),
    };
@endphp
<div class="balance-figures">
    <div><span>{{ __('Available to withdraw') }}</span><b>@eur($balance['available'])</b></div>
    <div><span>{{ __('On its way to the balance') }}</span><b class="muted-figure">@eur($balance['pending'])</b></div>
</div>
<dl class="payout-dest">
    <dt>{{ __('Paid out to') }}</dt>
    <dd>
        @if ($balance['destination'])
            {{ $balance['destination']['name'] }} &middot; <span class="iban">{{ $balance['destination']['account'] }}</span>
        @else
            <span class="danger-text">{{ __('No bank account yet: add and verify it in the Mollie dashboard.') }}</span>
        @endif
    </dd>
    @if ($frequency)
        <dt>{{ __('Automatic payouts') }}</dt>
        <dd>{{ $frequency }}</dd>
    @endif
</dl>
