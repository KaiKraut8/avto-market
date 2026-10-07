<x-layouts.app :title="__('Your profile')" active="account">

<main class="wrap">
    <div class="page-title">
        <h1>{{ __('Hi, :name', ['name' => $user->firstName()]) }}</h1>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn ghost">{{ __('Log out') }}</button>
        </form>
    </div>

    @if (session('status') === 'profile-information-updated')
        <div class="notice">{{ __('Profile saved.') }}</div>
    @elseif (session('status') === 'password-updated')
        <div class="notice">{{ __('Password changed.') }}</div>
    @elseif (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @elseif (session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif

    <div class="detail">
        <div>
            <section class="panel">
                <h2>{{ __('Your cars (:count)', ['count' => $cars->count()]) }}</h2>
                @if ($cars->isNotEmpty())
                    <div class="my-cars">
                        @foreach ($cars as $c)
                            <a class="my-car" href="{{ route('cars.show', $c) }}">
                                @if ($c->coverPhoto)
                                    <img src="{{ $c->coverPhoto->url() }}" alt="" loading="lazy">
                                @else
                                    <span class="mini-thumb">{{ $c->initial() }}</span>
                                @endif
                                <span class="my-car-text">
                                    <b>{{ $c->name }}</b>
                                    <small>
                                        {{ trans_choice(':count person looked|:count people looked', (int) $c->people) }} &middot;
                                        {{ trans_choice(':count inquiry|:count inquiries', (int) $c->inquiries_count) }}
                                        @if ($c->isPremium())
                                            &middot; <span class="gold">&#9813; {{ __('Premium') }}</span>
                                        @elseif ($c->isBoosted())
                                            &middot; <span class="amber">&#8679; {{ __('Pushed until :date', ['date' => $c->boosted_until->format('Y-m-d')]) }}</span>
                                        @endif
                                    </small>
                                </span>
                                <span class="price">@price($c->price)</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="hint">{{ __("You haven't listed a car yet.") }}</p>
                @endif
                <a class="btn accent" href="{{ route('cars.create') }}" style="margin-top:1rem">+ {{ __('Sell a car') }}</a>
            </section>

            @if ($cars->isNotEmpty())
                <section @class(['panel', 'insights', 'locked' => ! $user->hasPremium()])>
                    <h2><x-icon name="chart" :size="16" /> {{ __('Insights') }}</h2>
                    @if ($user->hasPremium())
                        <div class="table-scroll">
                            <table class="insights-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Car') }}</th>
                                        <th title="{{ __('Views in the last 30 days') }}">{{ __('Views (30 days)') }}</th>
                                        <th>{{ __('People') }}</th>
                                        <th>{{ __('Saves') }}</th>
                                        <th>{{ __('Inquiries') }}</th>
                                        <th title="{{ __('Share of people who looked and then contacted you') }}">{{ __('Contacted') }}</th>
                                        <th>{{ __('Deal') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cars as $c)
                                        <tr>
                                            <td><a href="{{ route('cars.show', $c) }}">{{ $c->name }}</a></td>
                                            <td>{{ $c->views_30 }}</td>
                                            <td>{{ (int) $c->people }}</td>
                                            <td>{{ $c->saves }}</td>
                                            <td>{{ $c->inquiries_count }}</td>
                                            <td>{{ $c->people ? round(100 * $c->inquiries_count / $c->people).'%' : '–' }}</td>
                                            <td>
                                                @if ($c->activeDeal)
                                                    <span class="deal-off">&minus;{{ $c->activeDeal->percentOff() }}%</span> <small class="hint">{{ __('until :date', ['date' => $c->activeDeal->ends_at->format('Y-m-d')]) }}</small>
                                                @elseif ($c->price !== null)
                                                    <a href="{{ route('cars.show', $c) }}#deal">{{ __('Start one') }}</a>
                                                @else
                                                    <span class="hint">{{ __('Needs a price') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="hint">{{ __('Lots of saves but few inquiries? A short special deal often turns savers into buyers: they are told the moment it starts.') }}</p>
                    @else
                        <div class="insights-teaser" aria-hidden="true">
                            <span></span><span></span><span></span><span></span>
                        </div>
                        <p>{{ __('See views over 30 days, saves, inquiries and how many lookers contacted you, for every car. Insights are part of premium for sellers.') }}</p>
                        <a class="btn gold" href="{{ route('premium.index') }}#sellers">&#9813; {{ __('See premium plans') }}</a>
                    @endif
                </section>
            @endif

            <section class="panel" id="saved-searches">
                <h2><x-icon name="search" :size="16" /> {{ __('Saved searches') }}</h2>
                @if ($user->hasBuyerPremium())
                    <p class="hint">{{ __('New cars and deals that match are sent to your notifications right away.') }}</p>
                    @if ($searches->isNotEmpty())
                        <ul class="saved-searches">
                            @foreach ($searches as $search)
                                <li>
                                    <a href="{{ route('cars.index', ['q' => $search->query]) }}">{{ $search->label() }}</a>
                                    <form method="post" action="{{ route('saved-searches.destroy', $search) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn ghost small">{{ __('Remove') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($errors->search->any())
                        <ul class="form-errors">
                            @foreach ($errors->search->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($searches->count() < \App\Models\SavedSearch::LIMIT)
                        <form method="post" action="{{ route('saved-searches.store') }}" class="row">
                            @csrf
                            <div>
                                <label for="search_query">{{ __('Search') }}</label>
                                <input type="text" id="search_query" name="query" maxlength="80" placeholder="{{ __('e.g. BMW, Audi, Ljubljana') }}" value="{{ old('query') }}">
                            </div>
                            <div>
                                <label for="search_max">{{ __('Highest price') }}</label>
                                <input type="text" id="search_max" name="max_price" inputmode="decimal" placeholder="30.000" value="{{ old('max_price') }}">
                            </div>
                            <button type="submit" class="btn">{{ __('Save search') }}</button>
                        </form>
                    @endif
                @else
                    <p>{{ __('Save searches like “BMW up to 30.000 €” and hear about new matching cars and deals first. Saved searches are part of premium for buyers.') }}</p>
                    <a class="btn gold" href="{{ route('premium.index') }}#buyers">&#9813; {{ __('Premium for buyers') }}</a>
                @endif
            </section>
        </div>

        <aside>
            @if ($user->hasPremium())
                <section class="panel premium-status">
                    <h2>&#9813; {{ __('Premium seller') }}</h2>
                    <p>{{ __('Top placement, special deals, insights and interest alerts for every car you list.') }}</p>
                    <p class="hint">{{ __(':plan plan, active until :date.', ['plan' => __(ucfirst($user->premium_plan)), 'date' => $user->premium_until->format('Y-m-d')]) }}</p>
                </section>
            @endif
            @if ($user->hasBuyerPremium())
                <section class="panel premium-status">
                    <h2>&#9813; {{ __('Premium buyer') }}</h2>
                    <p>{{ __('Member prices on deals, alerts for deals on cars like yours, and saved searches.') }}</p>
                    <p class="hint">{{ __(':plan plan, active until :date.', ['plan' => __(ucfirst($user->buyer_premium_plan)), 'date' => $user->buyer_premium_until->format('Y-m-d')]) }}</p>
                </section>
            @endif
            @if (! $user->hasPremium() || ! $user->hasBuyerPremium())
                <section class="panel upsell">
                    <span class="upsell-crown" aria-hidden="true">&#9813;</span>
                    <h2>{{ __('Go premium') }}</h2>
                    @unless ($user->hasPremium())
                        <p><b>{{ __('Selling?') }}</b> {{ __('Top placement, special deals and insights. From :price a month.', ['price' => \App\Support\Money::eur(\App\Services\Pricing::monthly())]) }}</p>
                    @endunless
                    @unless ($user->hasBuyerPremium())
                        <p><b>{{ __('Buying?') }}</b> {{ __('Member prices, deal alerts and saved searches. From :price a month.', ['price' => \App\Support\Money::eur(\App\Services\Pricing::buyerMonthly())]) }}</p>
                    @endunless
                    <a class="btn gold" href="{{ route('premium.index') }}">&#9813; {{ __('See premium plans') }}</a>
                </section>
            @endif

            <section class="panel">
                <h2>{{ __('Profile') }}</h2>
                <p class="hint">{{ __('Buyers who contact you about a car see your name, phone and email.') }}</p>
                @if ($errors->updateProfileInformation->any())
                    <ul class="form-errors">
                        @foreach ($errors->updateProfileInformation->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('user-profile-information.update') }}">
                    @csrf @method('PUT')
                    <div class="field">
                        <label>{{ __('Email (login)') }}</label>
                        <input type="email" value="{{ $user->email }}" disabled>
                    </div>
                    <div class="field">
                        <label for="name">{{ __('Full name') }}</label>
                        <input type="text" id="name" name="name" maxlength="60" required value="{{ old('name', $user->name) }}">
                    </div>
                    <div class="field">
                        <label for="phone">{{ __('Phone') }}</label>
                        <input type="tel" id="phone" name="phone" maxlength="25" required value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="field">
                        <label for="location">{{ __('Location') }}</label>
                        <input type="text" id="location" name="location" maxlength="80" placeholder="{{ __('e.g. Ljubljana') }}" value="{{ old('location', $user->location) }}">
                    </div>
                    <div class="field">
                        <label for="country">{{ __('Country') }}</label>
                        <select id="country" name="country">
                            @foreach (config('countries') as $c)
                                <option value="{{ $c }}" @selected(old('country', $user->country ?? config('countries')[0]) === $c)>{{ __($c) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn accent">{{ __('Save profile') }}</button>
                </form>
            </section>

            <section class="panel">
                <h2>{{ __('Change password') }}</h2>
                @if ($errors->updatePassword->any())
                    <ul class="form-errors">
                        @foreach ($errors->updatePassword->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('user-password.update') }}">
                    @csrf @method('PUT')
                    <div class="field">
                        <label for="current_password">{{ __('Current password') }}</label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="field">
                        <label for="password">{{ __('New password') }}</label>
                        <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label for="password_confirmation">{{ __('Repeat new password') }}</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" minlength="8" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn ghost">{{ __('Change password') }}</button>
                </form>
            </section>
        </aside>
    </div>
</main>

</x-layouts.app>
