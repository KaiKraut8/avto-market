@php
    $mail = '<a href="'.e(company_mailto()).'">'.e(config('company.email')).'</a>';
    $session = trans_choice(':count hour after your last visit|:count hours after your last visit', intdiv((int) config('session.lifetime'), 60));
    $cookies = [
        [config('session.cookie'), __('Keeps you logged in, remembers your language and protects forms.'), $session],
        ['XSRF-TOKEN', __('Protects forms against forgery from other sites.'), $session],
        [\App\Support\Visitor::COOKIE, __('A random id that keeps your wishlist, the cars you viewed and the sellers you contacted, without an account. It doesn\'t say who you are.'), trans_choice(':count year|:count years', 1)],
        ['kai_cookies_seen', __('Remembers that you have seen the cookie notice, so it isn\'t shown again.'), trans_choice(':count year|:count years', 1)],
        ['remember_web_…', __('Only if you tick "Keep me logged in": keeps you logged in until you log out.'), trans_choice(':count day|:count days', 400)],
    ];
@endphp
<x-layouts.app :title="__('Cookie policy')" active="cookies">

<main class="wrap why-page legal-page">
    <div class="why-hero">
        <x-icon name="cookie" :size="56" class="why-hero-icon" />
        <div>
            <p class="eyebrow">{{ __('Your data') }}</p>
            <h1>{{ __('Cookie policy') }}</h1>
            <p class="lede">{{ __('KAI Garage uses only the cookies it needs to work. No advertising, analytics or tracking cookies, and none from other companies.') }}</p>
            <p class="hint legal-date">{{ __('Last updated: :date', ['date' => \Illuminate\Support\Carbon::parse(config('company.legal_updated'))->isoFormat('LL')]) }}</p>
        </div>
    </div>

    <section class="panel">
        <h2>{{ __('What a cookie is') }}</h2>
        <p>{{ __('A cookie is a small text file a website stores in your browser, so it can recognise the browser on the next page or visit.') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('The cookies we use') }}</h2>
        <div class="table-scroll">
            <table class="legal-table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('What it does') }}</th><th>{{ __('How long') }}</th></tr></thead>
                <tbody>
                    @foreach ($cookies as [$name, $purpose, $keep])
                        <tr><th scope="row"><code>{{ $name }}</code></th><td>{{ $purpose }}</td><td>{{ $keep }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint">{{ __('All of them are set by KAI Garage itself and are strictly necessary.') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Why we don\'t ask for consent') }}</h2>
        <p>{{ __('Cookies that are strictly necessary for a site to work, or for a service you asked for, don\'t need consent under the EU ePrivacy rules and Slovenia\'s Electronic Communications Act (ZEKom-2). That\'s why the cookie notice on the site only informs you and doesn\'t ask for consent. If we ever add other cookies, we will ask you first.') }}</p>
    </section>

    <section class="panel">
        <h2>{{ __('Other companies') }}</h2>
        <ul class="legal-list">
            <li><b>{{ __('Payments') }}</b>{{ __('When you pay, you go to Mollie\'s payment page, which uses its own cookies under Mollie\'s cookie policy.') }}</li>
            <li><b>{{ __('Map') }}</b>{{ __('Our address links to Google Maps. Nothing is loaded from Google until you click the link.') }}</li>
            <li><b>{{ __('Fonts') }}</b>{{ __('The fonts are served from our own server, not from a font provider.') }}</li>
        </ul>
    </section>

    <section class="panel">
        <h2>{{ __('Turning cookies off') }}</h2>
        <p>{{ __('You can delete or block cookies in your browser\'s settings. Without them you can\'t log in or keep a wishlist, and forms won\'t send.') }}</p>
        <p>{!! __('Questions? Write to :email. How we handle personal data is explained in our :link.', ['email' => $mail, 'link' => '<a href="'.route('privacy').'">'.e(__('Privacy policy')).'</a>']) !!}</p>
    </section>
</main>

</x-layouts.app>
