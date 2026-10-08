@php
    use App\Support\Money;
    $commission = round($example * $rate / 100, 2);
@endphp
<x-layouts.app :title="__('How buying works')" active="how-buying">

<main class="wrap why-page how-buying">
    <div class="why-hero">
        <x-icon name="tag" :size="56" class="why-hero-icon" />
        <div>
            <p class="eyebrow">{{ __('Before you buy or sell') }}</p>
            <h1>{{ __('How buying works') }}</h1>
            <p class="lede">{{ __('Every car on Vozi is sold through the site. :rate% of the price goes to Vozi, the rest to the seller.', ['rate' => $rate]) }}</p>
        </div>
    </div>

    <section class="panel example-calc">
        <h2>{{ __('Example: a car listed at :price', ['price' => Money::price($example)]) }}</h2>
        <div class="calc-rows">
            <div><span>{{ __('Price of the car') }}</span><b>@price($example)</b></div>
            <div class="calc-online"><span>{{ __('You pay online now (:rate%, to Vozi)', ['rate' => $rate]) }}</span><b>@eur($commission)</b></div>
            <div><span>{{ __('You pay the seller at the handover') }}</span><b>@price($example - $commission)</b></div>
            <div class="calc-total"><span>{{ __('Together, the buyer pays') }}</span><b>@price($example)</b></div>
        </div>
        <p class="hint">{{ __('The buyer pays exactly the listed price. The seller receives :percent% of it and Vozi :rate%.', ['percent' => 100 - $rate, 'rate' => $rate]) }}</p>
    </section>

    <div class="how-grid">
        <section class="panel">
            <h2>{{ __('For buyers') }}</h2>
            <ol class="steps">
                <li>{{ __('Find a car and press "Buy this car".') }}</li>
                <li>{{ __('Pay :rate% of the price online, by card, PayPal or paysafecard. The car is reserved for you right away and nobody else can buy it.', ['rate' => $rate]) }}</li>
                <li>{{ __('The seller gets your contact details and arranges the handover with you, normally within :days days.', ['days' => $days]) }}</li>
                <li>{{ __('At the handover you pay the seller the rest of the price and take the car.') }}</li>
                <li>{{ __('If the seller can\'t complete the sale, they cancel it and you get your :rate% back in full, to the same payment method.', ['rate' => $rate]) }}</li>
            </ol>
        </section>
        <section class="panel">
            <h2>{{ __('For sellers') }}</h2>
            <ol class="steps">
                <li>{{ __('Listing a car is free. By listing it you agree that :rate% of its selling price goes to Vozi when it is sold.', ['rate' => $rate]) }}</li>
                <li>{{ __('When a buyer reserves your car, they have already paid the :rate% online. You get their details and an alert.', ['rate' => $rate]) }}</li>
                <li>{{ __('At the handover the buyer pays you the rest (:percent% of the price). Then press "Confirm the sale" on the car\'s page.', ['percent' => 100 - $rate]) }}</li>
                <li>{{ __('If the sale falls through, press "Cancel the sale": the buyer is refunded and the car is for sale again.') }}</li>
                <li>{{ __('Sold the car another way? Mark it as sold and pay the :rate% commission yourself. Until it is paid you can\'t list new cars.', ['rate' => $rate]) }}</li>
            </ol>
        </section>
    </div>

    <section class="panel">
        <h2>{{ __('Good to know') }}</h2>
        <ul class="perks">
            <li>{{ __('The commission is the only fee for selling. Premium and Push forward are optional extras.') }}</li>
            <li>{{ __('Questions about a car before buying? Use "Contact seller" on its page; the purchase itself always goes through "Buy this car".') }}</li>
            <li>{{ __('Payments are handled by our payment provider, Mollie. Vozi never sees your card details.') }}</li>
        </ul>
    </section>
</main>

</x-layouts.app>
