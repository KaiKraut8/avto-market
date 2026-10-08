@php
    $mail = '<a href="'.e(company_mailto()).'">'.e(config('company.email')).'</a>';
    $rows = [
        [__('Account'), __('Name, email, phone, location, country and password (stored only as a one-way hash).'), __('To run your account, show your listings and let buyers reach you.'), __('Contract'), __('Until your account is deleted.')],
        [__('Car listings'), __('Car details, price, description and photos.'), __('To publish your car on the site.'), __('Contract'), __('While the car is listed. Deleted listings are hidden from everyone; we erase them for good on request.')],
        [__('Messages to sellers'), __('Your name, email, phone and message.'), __('To pass your question on to the seller.'), __('Your request (steps before a contract)'), __('As long as the listing exists, or until you ask us to delete them.')],
        [__('Purchases and sales'), __('Buyer, seller, car, price and the commission.'), __('To reserve the car, put buyer and seller in touch and charge the commission.'), __('Contract and legal obligation'), __('10 years, as tax and accounting law requires.')],
        [__('Payments and subscriptions'), __('Amount, payment method, status and the payment provider\'s reference. Never your card or PayPal details.'), __('To take payments, renew subscriptions until you cancel them, and refund.'), __('Contract and legal obligation'), __('10 years, as tax and accounting law requires.')],
        [__('Wishlist, saved searches and alerts'), __('The cars and searches you saved and the alerts we sent you.'), __('To show your wishlist and send the alerts you asked for.'), __('Contract'), __('Until you remove them or your account is deleted.')],
        [__('Visitor statistics'), __('A random visitor id, which cars it viewed and when. No name, no IP address.'), __('To show view counts and who is watching a car right now, and to keep a wishlist without an account.'), __('Legitimate interest'), __('Views are kept as statistics; "watching now" lasts 40 seconds.')],
        [__('Security'), __('IP address and browser in your login session; email and IP address of failed logins.'), __('To keep you logged in and stop password guessing.'), __('Legitimate interest'), __('The session ends 2 hours after your last visit; failed logins are forgotten after 1 minute.')],
    ];
@endphp
<x-layouts.app :title="__('Privacy policy')" active="privacy">

<main class="wrap why-page legal-page">
    <div class="why-hero">
        <x-icon name="shield" :size="56" class="why-hero-icon" />
        <div>
            <p class="eyebrow">{{ __('Your data') }}</p>
            <h1>{{ __('Privacy policy') }}</h1>
            <p class="lede">{{ __('What personal data Vozi collects, why, how long we keep it and what your rights are.') }}</p>
            <p class="hint legal-date">{{ __('Last updated: :date', ['date' => \Illuminate\Support\Carbon::parse(config('company.legal_updated'))->isoFormat('LL')]) }}</p>
        </div>
    </div>

    <section class="panel">
        <h2>{{ __('Who is responsible') }}</h2>
        <p>{{ __('The controller of your personal data is :company, :address.', ['company' => config('company.name'), 'address' => config('company.address')]) }}</p>
        <p>{!! __('For anything about your data, write to :email.', ['email' => $mail]) !!}</p>
    </section>

    <section class="panel">
        <h2>{{ __('What we collect and why') }}</h2>
        <p>{{ __('We only collect what the site needs to work. We don\'t sell personal data, show ads or use analytics or tracking services.') }}</p>
        <div class="table-scroll">
            <table class="legal-table">
                <thead>
                    <tr><th>{{ __('What') }}</th><th>{{ __('Data') }}</th><th>{{ __('Why') }}</th><th>{{ __('Legal basis') }}</th><th>{{ __('How long') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as [$what, $data, $why, $basis, $keep])
                        <tr><th scope="row">{{ $what }}</th><td>{{ $data }}</td><td>{{ $why }}</td><td>{{ $basis }}</td><td>{{ $keep }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint">{{ __('Legal bases under the GDPR: contract, Art. 6(1)(b); legal obligation, Art. 6(1)(c); legitimate interest, Art. 6(1)(f).') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Who sees your data') }}</h2>
        <ul class="legal-list">
            <li><b>{{ __('Everyone') }}</b>{{ __('Your listings, with the location you entered, are public.') }}</li>
            <li><b>{{ __('Buyers who contact you') }}</b>{{ __('When a visitor sends you a message through "Contact seller", they see your name, phone and email.') }}</li>
            <li><b>{{ __('Buyer and seller of a car') }}</b>{{ __('When a car is reserved, the buyer and the seller get each other\'s name, email and phone to arrange the handover.') }}</li>
            <li><b>{{ __('Mollie B.V., Amsterdam') }}</b>{{ __('Our payment provider. It receives your name, email and the payment. Card and PayPal details go straight to Mollie, never to us. Mollie\'s own privacy statement also applies.') }}</li>
            <li><b>{{ __('Our hosting and email providers') }}</b>{{ __('They store the site and send emails (such as password resets) on our behalf, under a data processing agreement.') }}</li>
            <li><b>{{ __('Authorities') }}</b>{{ __('Only when the law requires it.') }}</li>
        </ul>
        <p>{{ __('Your data is processed in the European Union.') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Your rights') }}</h2>
        <ul class="perks">
            <li>{{ __('See what data we have about you and get a copy of it.') }}</li>
            <li>{{ __('Correct it. Most of it you can change yourself on your account page.') }}</li>
            <li>{{ __('Have it deleted, including your whole account.') }}</li>
            <li>{{ __('Restrict or object to how we use it.') }}</li>
            <li>{{ __('Get the data you gave us in a machine-readable format.') }}</li>
            <li>{{ __('Complain to the Information Commissioner of the Republic of Slovenia (Dunajska cesta 22, 1000 Ljubljana, www.ip-rs.si) or the data protection authority in your country.') }}</li>
        </ul>
        <p>{!! __('To use a right, write to :email from the email address of your account. We answer within one month and may ask you to confirm it\'s you.', ['email' => $mail]) !!}</p>
        <p class="hint">{{ __('When an account is deleted, we keep only what the law requires (payments and sales) until the legal period ends.') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Security') }}</h2>
        <p>{{ __('Passwords are stored only as a one-way hash, connections are encrypted, payments go through Mollie, and every form is protected against forgery. Logging in is limited to 5 attempts a minute.') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Cookies') }}</h2>
        <p>{!! __('We only use cookies the site needs to work. The details are in our :link.', ['link' => '<a href="'.route('cookies').'">'.e(__('Cookie policy')).'</a>']) !!}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Children and changes') }}</h2>
        <p>{{ __('Vozi is not meant for children under 15, and only adults can buy or sell a car.') }}</p>
        <p>{{ __('If we change this policy, we update the date at the top. We tell account holders about important changes before they apply.') }}</p>
    </section>
</main>

</x-layouts.app>
