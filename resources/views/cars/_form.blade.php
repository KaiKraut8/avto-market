{{-- The car form: full width when posting a new car, in the side column when editing one --}}
@php($new = ! $car->exists)
<form method="post" action="{{ $new ? route('cars.store') : route('cars.update', $car) }}" class="car-form" enctype="multipart/form-data" data-shrink-form>
    @csrf
    @unless ($new) @method('PUT') @endunless

    @if ($errors->any())
        <ul class="form-errors wide">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="field">
        <label for="name">{{ __('Name') }}</label>
        <input type="text" id="name" name="name" maxlength="50" required placeholder="{{ __('e.g. BMW 320d Touring') }}" value="{{ old('name', $car->name) }}">
    </div>
    <div class="field">
        <label for="price">{{ __('Price (€)') }}</label>
        <input type="number" id="price" name="price" min="0" step="100" placeholder="{{ __('e.g. 24900') }}" value="{{ old('price', $car->price !== null ? (int) $car->price : '') }}">
    </div>
    <div class="field-row">
        <div class="field">
            <label for="location">{{ __('Location') }} <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="location" name="location" minlength="2" maxlength="80" required placeholder="{{ __('e.g. Ljubljana') }}" value="{{ old('location', $car->location) }}">
        </div>
        <div class="field">
            <label for="country">{{ __('Country') }} <span class="req" aria-hidden="true">*</span></label>
            <select id="country" name="country" required>
                @foreach (config('countries') as $country)
                    <option value="{{ $country }}" @selected(old('country', $car->country ?? config('countries')[0]) === $country)>{{ __($country) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="field wide">
        <label for="description">{{ __('Description') }}</label>
        <textarea id="description" name="description" rows="6" maxlength="5000" placeholder="{{ __('Condition, history, equipment, anything a buyer should know...') }}">{{ old('description', $car->description) }}</textarea>
    </div>

    @if ($new)
        <div class="field wide">
            <label for="new-photos">{{ __('Photos') }}</label>
            <input type="file" id="new-photos" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
            <p class="hint file-hint">{{ __('Add one or more. JPG, PNG, WebP or GIF; big photos are shrunk automatically.') }}</p>
        </div>
    @endif

    <p class="hint profile-note wide">{{ __('Buyers reach you with the name, phone and email from your profile.') }} <a href="{{ route('account') }}">{{ __('Edit profile') }}</a></p>

    @if ($new && auth()->user()->hasPremium())
        <fieldset class="plan-pick">
            <legend>{{ __('Listing') }}</legend>
            <div class="plan plan-premium">
                <span class="plan-card included">
                    <b><span aria-hidden="true">&#9813;</span> {{ __('Premium listing') }}</b>
                    <span class="plan-price">{{ __('Included') }}</span>
                    <small>{{ __('Your premium account puts this car at the top of All cars, in gold.') }}</small>
                </span>
            </div>
        </fieldset>
    @elseif ($new)
        @php($plan = old('plan', 'free'))
        <fieldset class="plan-pick">
            <legend>{{ __('How do you want to list it?') }}</legend>
            <label class="plan">
                <input type="radio" name="plan" value="free" @checked(! in_array($plan, ['boost', 'premium'], true))>
                <span class="plan-card">
                    <b>{{ __('Free listing') }}</b>
                    <span class="plan-price">0 €</span>
                    <small>{{ __('Listed with all the other cars.') }}</small>
                </span>
            </label>
            {{-- push forward is only offered to sellers without premium --}}
            <label class="plan plan-boost">
                <input type="radio" name="plan" value="boost" @checked($plan === 'boost')>
                <span class="plan-card">
                    <b><span aria-hidden="true">&#8679;</span> {{ __('Push forward') }}</b>
                    <span class="plan-price">@eur(\App\Services\Pricing::boostWeekly()) <i>/ {{ __('week') }}</i></span>
                    <small>{{ __('Shown first among the regular cars for 7 days.') }}</small>
                </span>
            </label>
            <label class="plan plan-premium">
                <input type="radio" name="plan" value="premium" @checked($plan === 'premium')>
                <span class="plan-card">
                    <b><span aria-hidden="true">&#9813;</span> {{ __('Premium') }}</b>
                    <span class="plan-price">{{ __('from :price', ['price' => \App\Support\Money::eur(\App\Services\Pricing::monthly())]) }} <i>/ {{ __('month') }}</i></span>
                    <small>{{ __('For your account: every car you list goes to the top, in gold.') }}</small>
                </span>
            </label>
            {{-- only shown once Premium is picked --}}
            <div class="premium-billing">
                <label class="plan plan-premium">
                    <input type="radio" name="billing" value="monthly" @checked(old('billing', 'monthly') !== 'yearly')>
                    <span class="plan-card">
                        <b>{{ __('Monthly') }}</b>
                        <span class="plan-price">@eur(\App\Services\Pricing::monthly()) <i>/ {{ __('month') }}</i></span>
                        <small>{{ __('Cancel any time.') }}</small>
                    </span>
                </label>
                <label class="plan plan-premium">
                    <input type="radio" name="billing" value="yearly" @checked(old('billing') === 'yearly')>
                    <span class="plan-card">
                        <b>{{ __('Yearly') }}</b>
                        <span class="plan-price">@eur(\App\Services\Pricing::yearly()) <i>/ {{ __('year') }}</i></span>
                        <small><s>@eur(\App\Services\Pricing::yearlyAtMonthlyRate())</s> {{ __('if paid monthly.') }}</small>
                        <span class="save-badge">{{ __('Save :percent%', ['percent' => \App\Services\Pricing::yearlySaving()]) }}</span>
                    </span>
                </label>
            </div>
            <p class="hint demo-note">{{ __('Push forward and premium are paid on the next page, by card, PayPal or paysafecard. The car is saved either way.') }}</p>
        </fieldset>
    @endif

    @if ($new)
        @php($rate = \App\Models\CarSale::rate())
        <div class="commission-box">
            <b>{{ __('Selling costs :rate% of the price', ['rate' => $rate]) }}</b>
            <p>{{ __('Listing is free. When the car is sold, :rate% of the price goes to KAI Garage: the buyer pays it online when they reserve the car, and pays you the other :percent% at the handover.', ['rate' => $rate, 'percent' => 100 - $rate]) }}
                <a href="{{ route('how-buying') }}" target="_blank">{{ __('How buying works') }}</a></p>
            <label class="remember"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))> {{ __('I agree that :rate% of the selling price goes to KAI Garage.', ['rate' => $rate]) }}</label>
        </div>
    @endif

    <button type="submit" class="btn accent">{{ $new ? __('Post car') : __('Save') }}</button>
</form>
