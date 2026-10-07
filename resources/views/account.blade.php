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
        </div>

        <aside>
            @if ($user->hasPremium())
                <section class="panel premium-status">
                    <h2>&#9813; {{ __('Premium seller') }}</h2>
                    <p>{{ __('Every car you list is shown first in All cars, in gold.') }}</p>
                    <p class="hint">{{ __(':plan plan, active until :date.', ['plan' => __(ucfirst($user->premium_plan)), 'date' => $user->premium_until->format('Y-m-d')]) }}</p>
                </section>
            @else
                <section class="panel upsell">
                    <span class="upsell-crown" aria-hidden="true">&#9813;</span>
                    <h2>{{ __('Go premium') }}</h2>
                    <p>{{ __('Put every car you list at the top of All cars, in gold. From :price a month.', ['price' => \App\Support\Money::eur(\App\Services\Pricing::monthly())]) }}</p>
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
