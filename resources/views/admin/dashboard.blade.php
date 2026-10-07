<x-layouts.app :title="__('Admin')" active="admin">

<main class="wrap admin-page">
    <div class="page-title">
        <h1>{{ __('Earnings') }}</h1>
        <span class="count">{{ trans_choice(':count account|:count accounts', $accounts) }} &middot; {{ trans_choice(':count car|:count cars', $cars) }}</span>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif
    @if ($testMode)
        <div class="notice test-notice">{{ __('Test mode: Mollie is not connected (MOLLIE_KEY in .env), so these are test payments and no real money is involved.') }}</div>
    @endif

    <section class="stat-row">
        <div class="stat"><span>{{ __('This month') }}</span><b>@eur($thisMonth)</b></div>
        <div class="stat"><span>{{ __('Last month') }}</span><b>@eur($lastMonth)</b></div>
        <div class="stat"><span>{{ __('All time') }}</span><b>@eur($total)</b></div>
        <div class="stat" title="{{ __('What running subscriptions bring in per month') }}"><span>{{ __('Recurring per month') }}</span><b>@eur($mrr)</b>
            <small>{{ __(':sellers premium sellers, :buyers premium buyers', ['sellers' => $activeSellers, 'buyers' => $activeBuyers]) }}</small></div>
    </section>

    <div class="detail">
        <div>
            <section class="panel">
                <h2>{{ __('Latest payments') }}</h2>
                @if ($payments->isEmpty())
                    <p class="hint">{{ __('No payments yet.') }}</p>
                @else
                    <div class="table-scroll">
                        <table class="admin-table">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('For') }}</th><th>{{ __('Method') }}</th><th>{{ __('Amount') }}</th></tr></thead>
                            <tbody>
                                @foreach ($payments as $p)
                                    <tr>
                                        <td>{{ $p->created_at->format('Y-m-d H:i') }}</td>
                                        <td>{{ $p->user?->name }}<br><small class="hint">{{ $p->user?->email }}</small></td>
                                        <td>{{ $p->description }}</td>
                                        <td>{{ \App\Models\Payment::methodName($p->method) }}</td>
                                        <td class="num">@eur($p->amount) <span @class(['pay-status', 'pay-'.$p->status])>{{ $p->statusLabel() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($openSales->isNotEmpty())
                <section class="panel">
                    <h2>{{ __('Open sales') }}</h2>
                    <div class="table-scroll">
                        <table class="admin-table">
                            <thead><tr><th>{{ __('Car') }}</th><th>{{ __('Seller') }}</th><th>{{ __('State') }}</th><th>{{ __('Commission') }}</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($openSales as $sale)
                                    <tr>
                                        <td><a href="{{ route('cars.show', $sale->car_id) }}">{{ $sale->car->name }}</a></td>
                                        <td>{{ $sale->seller?->name }}</td>
                                        <td>
                                            @if ($sale->status === 'reserved')
                                                {{ __('Reserved by :name since :date', ['name' => $sale->buyer?->name, 'date' => $sale->updated_at->format('Y-m-d')]) }}
                                                @if ($sale->updated_at->lt(now()->subDays((int) config('pricing.reservation_days'))))
                                                    <span class="danger-text">· {{ __('waiting too long') }}</span>
                                                @endif
                                            @else
                                                <span class="danger-text">{{ __('Sold elsewhere, commission unpaid') }}</span>
                                            @endif
                                        </td>
                                        <td class="num">@eur($sale->commission)</td>
                                        <td>
                                            @if ($sale->status === 'reserved')
                                                <form method="post" action="{{ route('sales.cancel', $sale) }}" data-confirm="{{ __('Cancel the sale? The buyer gets their money back and the car is for sale again.') }}">
                                                    @csrf
                                                    <button type="submit" class="btn danger small">{{ __('Cancel and refund') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="panel">
                <h2>{{ __('Where the money comes from') }}</h2>
                <div class="split-grid">
                    <ul class="split-list">
                        @foreach (['commission' => __('Sales commission'), 'seller' => __('Premium seller'), 'buyer' => __('Premium buyer'), 'boost' => __('Push forward')] as $key => $label)
                            <li><span>{{ $label }}</span><b>@eur($byProduct[$key]->total ?? 0)</b></li>
                        @endforeach
                    </ul>
                    <ul class="split-list">
                        @forelse ($byMethod as $m)
                            <li><span>{{ \App\Models\Payment::methodName($m->method) }}</span><b>@eur($m->total)</b></li>
                        @empty
                            <li class="hint">{{ __('No payments yet.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>

        <aside>
            <section class="panel wallet">
                <h2><x-icon name="card" :size="16" /> {{ __('Payment account') }}</h2>
                @if ($balance)
                    @include('admin._balance')
                    <a class="btn gold big wallet-btn" href="{{ route('admin.withdraw.create') }}">{{ __('Withdraw to bank') }}</a>
                @else
                    <p class="danger-text">{{ $balanceError }}</p>
                @endif
                <p class="hint">{{ __('Card, PayPal and paysafecard payments land on your Mollie balance (minus Mollie\'s fees) and are paid out to the bank account verified with Mollie.') }}</p>
            </section>

            <section class="panel">
                <h2>{{ __('Withdrawals') }}</h2>
                @forelse ($payouts as $p)
                    <div class="payout-row">
                        <span>{{ \Illuminate\Support\Carbon::parse($p['created_at'])->format('Y-m-d') }}</span>
                        <b>@eur($p['amount'])</b>
                        <span class="pay-status pay-{{ $p['status'] === 'completed' ? 'paid' : ($p['status'] === 'failed' ? 'failed' : 'open') }}">{{ (new \App\Models\Payout(['status' => $p['status']]))->statusLabel() }}</span>
                    </div>
                @empty
                    <p class="hint">{{ __('No withdrawals yet.') }}</p>
                @endforelse
            </section>
        </aside>
    </div>
</main>

</x-layouts.app>
