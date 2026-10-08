<x-layouts.app :title="__('Best car offers in town')" active="home">

<section class="home-hero" data-hero>
    <x-hero-scene />

    <div class="inner hero-grid">
        <div class="hero-copy">
            <p class="pill"><span class="live-dot"></span>{{ trans_choice(':count car in the garage right now|:count cars in the garage right now', $cars->count()) }}</p>
            <h1>{{ __('The best car offers') }} <span>{{ __('in town.') }}</span></h1>
            <p class="hero-lede">{{ __('Hand-picked cars with real photos. Fair prices, no surprises: find your next car before someone else does.') }}</p>
            <x-search-panel :bounds="$bounds" :hero="true" :placeholder="__('What car are you looking for? e.g. BMW, Mercedes, Ljubljana')" />
            <dl class="hero-facts">
                @if ($lowest !== null)
                    <div><dt>{{ __('Prices from') }}</dt><dd>@price($lowest)</dd></div>
                @endif
                @if ($bounds['yearMin'] && $bounds['yearMax'] > $bounds['yearMin'])
                    <div class="fact-teal"><dt>{{ __('Years') }}</dt><dd>{{ $bounds['yearMin'] }}–{{ $bounds['yearMax'] }}</dd></div>
                @endif
                @if ($interested > 0)
                    <div class="fact-violet"><dt>{{ __('Interested buyers') }}</dt><dd>{{ $interested }}</dd></div>
                @endif
            </dl>
        </div>

        @if ($topPick)
            {{-- the featured car floats and can be turned around by dragging; the back shows its key facts --}}
            <div class="hero-car-stage" data-depth="-.6">
                <a class="deal-coin" href="#deals" data-depth="-1.4">
                    <span class="deal-coin-face front"><b>{{ $maxOff ? '−'.$maxOff.'%' : '€' }}</b><small>{{ $maxOff ? __('Deals') : __('Hot prices') }}</small></span>
                    <span class="deal-coin-face back"><b>{{ $wall->count() }}</b><small>{{ $maxOff ? __('deals now') : __('top prices') }}</small></span>
                </a>
                <div class="hero-car" data-flip-card>
                    <div class="hero-car-inner">
                        <a class="hero-car-face front" href="{{ route('cars.show', $topPick) }}" draggable="false">
                            <span class="hero-car-tag">{{ $topLabel }}</span>
                            <div class="hero-car-frame"><x-car-photo :car="$topPick" :lazy="false" /></div>
                            <div class="hero-car-info">
                                <strong>{{ $topPick->name }}</strong>
                                <span class="price">@price($topPick->price)</span>
                            </div>
                        </a>
                        <div class="hero-car-face back">
                            <span class="hero-car-tag">{{ __('At a glance') }}</span>
                            <dl class="back-facts">
                                <div><dt>{{ __('Year') }}</dt><dd>{{ $topPick->year ?: '—' }}</dd></div>
                                <div><dt>{{ __('Price') }}</dt><dd>@price($topPick->price)</dd></div>
                                <div><dt>{{ __('Location') }}</dt><dd>{{ $topPick->locationLabel() ?: '—' }}</dd></div>
                                <div><dt>{{ __('People who looked') }}</dt><dd>{{ $topPick->people }}</dd></div>
                            </dl>
                            <a class="btn accent" href="{{ route('cars.show', $topPick) }}" draggable="false">{{ __('View this car') }} <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </div>
                </div>
                <p class="hero-car-hint"><span aria-hidden="true">&#8634;</span> {{ __('Drag the card to turn it around') }}</p>
            </div>
        @endif
    </div>
    <a class="scroll-cue" href="#highlights" aria-label="{{ __('Scroll to the highlights') }}"><span></span></a>
</section>

<main class="wrap home">
    <div class="journey" aria-hidden="true"><div class="journey-car"><x-car-top /></div></div>

    {{-- the deal wall: the running deals, or the hottest prices when there are none --}}
    @if ($wall->isNotEmpty())
        <section class="deal-wall" id="deals">
            <div class="deal-ticker" aria-hidden="true">
                <div class="deal-ticker-track">
                    @foreach ([0, 1] as $copy)
                        @foreach ($wall as $car)
                            <span class="tick"><b>{{ $car->activeDeal ? '−'.$car->activeDeal->percentOff().'%' : __('Top price') }}</b> {{ $car->name }} <i>@price($car->activeDeal ? $car->activeDeal->deal_price : $car->price)</i></span>
                        @endforeach
                    @endforeach
                </div>
            </div>
            <div class="section-head deal-head">
                <div>
                    <p class="eyebrow rose">{{ $wallIsDeals ? __('Price drops right now') : __('The best prices in the garage') }}</p>
                    <h2 class="big-title deal-title">{{ $wallIsDeals ? __("Deals you can't miss") : __('Hot prices this week') }}</h2>
                </div>
                <a href="{{ $wallIsDeals ? route('deals.index') : route('cars.index', ['sort' => 'price_asc']) }}">{{ $wallIsDeals ? __('All deals') : __('See every car') }} &rarr;</a>
            </div>
            <div class="deal-cards">
                @foreach ($wall as $i => $car)
                    @php($deal = $car->activeDeal)
                    <a @class(['deal-card', 'featured' => $i === 0, 'tone-'.($i % 6)]) href="{{ route('cars.show', $car) }}" style="--i: {{ $i }}" data-tilt>
                        <span class="deal-burst" aria-hidden="true">{{ $deal ? '−'.$deal->percentOff().'%' : __('Top price') }}</span>
                        <div class="deal-card-photo"><x-car-photo :car="$car" /></div>
                        <div class="deal-card-body">
                            <h3>{{ $car->name }}@if ($car->year) <span class="offer-year">{{ $car->year }}</span>@endif</h3>
                            <div class="deal-card-price">
                                @if ($deal)
                                    <s>@price($deal->regular_price)</s>
                                    <b>@price($deal->deal_price)</b>
                                @else
                                    <b>@price($car->price)</b>
                                @endif
                            </div>
                            @if ($deal)
                                <span class="deal-save">{{ __('You save :amount', ['amount' => \App\Support\Money::price($deal->regular_price - $deal->deal_price)]) }}</span>
                                <small class="deal-ends"><x-icon name="clock" :size="13" /> {{ trans_choice('Ends in :count day|Ends in :count days', max(1, (int) ceil(now()->diffInHours($deal->ends_at, true) / 24))) }}</small>
                            @else
                                <small class="deal-ends">{{ $car->locationLabel() }}</small>
                            @endif
                            <span class="btn accent deal-go">{{ __('Grab it') }} <span aria-hidden="true">&rarr;</span></span>
                        </div>
                        <span class="deal-glare" aria-hidden="true"></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    <div class="section-head" id="highlights">
        <div>
            <p class="eyebrow">{{ __('Hot right now') }}</p>
            <h2 class="big-title">{{ $premiumCount ? __('Premium highlights') : __("Today's highlights") }}</h2>
        </div>
        <a href="{{ route('cars.index') }}">{{ __('See every car') }} &rarr;</a>
    </div>

    @if ($cars->isNotEmpty())
        <section class="offer-row">
            @foreach ($highlights as $i => $car)
                <a @class(['offer', 'premium' => $car->isPremium()]) href="{{ route('cars.show', $car) }}" style="--i: {{ $i }}" data-tilt>
                    @if ($car->isPremium())
                        <span class="premium-ribbon"><span aria-hidden="true">&#9813;</span> {{ __('Premium') }}</span>
                    @endif
                    <div class="offer-photo">
                        <x-car-photo :car="$car" />
                        <div class="offer-badges">
                            @if ($car->activeDeal)
                                <span class="tag tag-sale">{{ __('Deal −:percent%', ['percent' => $car->activeDeal->percentOff()]) }}</span>
                            @endif
                            @foreach ($badges[$car->id] as [$label, $kind])
                                <span class="tag tag-{{ $kind }}">{{ $label }}</span>
                            @endforeach
                        </div>
                        @if ($car->people > 0)
                            <span class="offer-eye" title="{{ __('People who looked at this car') }}"><x-icon name="eye" :size="15" /> {{ $car->people }}</span>
                        @endif
                        <span class="offer-price">@price($car->activeDeal ? $car->activeDeal->deal_price : $car->price)@if ($car->activeDeal) <s>@price($car->activeDeal->regular_price)</s>@endif</span>
                    </div>
                    <div class="offer-body">
                        <h3>{{ $car->name }}@if ($car->year) <span class="offer-year">{{ $car->year }}</span>@endif</h3>
                        <p class="offer-desc">{{ $car->description ?: __('Checked, photographed and ready for a test drive.') }}</p>
                        <span class="offer-go">{{ __('View this car') }} <span aria-hidden="true">&rarr;</span></span>
                    </div>
                </a>
            @endforeach
        </section>
    @else
        <div class="empty">{{ __('The garage is empty.') }} <a href="{{ route('cars.create') }}">{{ __('Add the first car') }}</a>.</div>
    @endif


    <section class="premium-promo" data-tilt data-tilt-max="4">
        <span class="promo-crown" aria-hidden="true">&#9813;</span>
        <div class="promo-copy">
            <p class="eyebrow">{{ __('Premium') }}</p>
            <h2>{{ __('More than a spot at the top.') }} <span>{{ __('Go premium.') }}</span></h2>
            <ul class="promo-perks">
                <li><b>{{ __('Sellers') }}</b> {{ __('Top placement in gold, special deals that reach interested buyers, and insights for every car.') }}</li>
                <li><b>{{ __('Buyers') }}</b> {{ __('Member prices on deals, alerts when cars like yours drop in price, and saved searches. From :price a month.', ['price' => \App\Support\Money::eur(\App\Services\Pricing::buyerMonthly())]) }}</li>
            </ul>
            <div class="promo-cta">
                <a class="btn gold big" href="{{ route('premium.index') }}">&#9813; {{ __('Get premium') }}</a>
                @auth
                    <a class="btn ghost big" href="{{ route('cars.create') }}">{{ __('List a car for free') }}</a>
                @else
                    <a class="btn ghost big" href="{{ route('register') }}">{{ __('Create a free seller account') }}</a>
                @endauth
            </div>
        </div>
        <div class="promo-plans">
            <a class="promo-plan" href="{{ route('premium.index') }}">
                <b>{{ __('Monthly') }}</b>
                <span class="promo-price">@eur(\App\Services\Pricing::monthly())</span>
                <small>{{ __('per month') }}</small>
            </a>
            <a class="promo-plan best" href="{{ route('premium.index') }}">
                <span class="save-badge">{{ __('Save :percent%', ['percent' => \App\Services\Pricing::yearlySaving()]) }}</span>
                <b>{{ __('Yearly') }}</b>
                <span class="promo-price">@eur(\App\Services\Pricing::yearly())</span>
                <small><s>@eur(\App\Services\Pricing::yearlyAtMonthlyRate())</s> {{ __('per year') }}</small>
            </a>
            <a class="promo-plan buyer" href="{{ route('premium.index') }}#buyers">
                <b>&#9813; {{ __('Premium buyer') }}</b>
                <span class="promo-price">@eur(\App\Services\Pricing::buyerMonthly())</span>
                <small>{{ __('per month') }}</small>
            </a>
            <a class="promo-plan boost" href="{{ route('cars.create') }}">
                <b>&#8679; {{ __('Push forward') }}</b>
                <span class="promo-price">@eur(\App\Services\Pricing::boostWeekly())</span>
                <small>{{ __('per week, no premium') }}</small>
            </a>
        </div>
    </section>

    <section class="why">
        <a class="why-item teal" href="{{ route('how-buying') }}" data-tilt>
            <x-icon name="check" />
            <h3>{{ __('Safe, simple buying') }}</h3>
            <p>{{ __('Pay a small part online and the car is reserved for you. The rest you pay the seller at the handover.') }}</p>
            <span class="why-more">{{ __('Learn more') }} &rarr;</span>
        </a>
        <a class="why-item blue" href="{{ route('why', 'real-photos') }}" data-tilt>
            <x-icon name="camera" />
            <h3>{{ __('Real photos, no stock images') }}</h3>
            <p>{{ __('What you see is the actual car sitting in our garage, not a picture from a catalogue.') }}</p>
            <span class="why-more">{{ __('Learn more') }} &rarr;</span>
        </a>
        <a class="why-item violet" href="{{ route('why', 'live-interest') }}" data-tilt>
            <x-icon name="eye" />
            <h3>{{ __('See the interest live') }}</h3>
            <p>{{ __("Every car shows how many people looked at it and who's viewing it right now. Good offers go fast.") }}</p>
            <span class="why-more">{{ __('Learn more') }} &rarr;</span>
        </a>
    </section>

    <section class="cta-band" data-tilt data-tilt-max="4">
        <div>
            <h2>{{ __('Found the one? Come for a test drive.') }}</h2>
            <p>{{ config('company.address') }} &middot; {{ opening_hours_summary() }}</p>
        </div>
        <div class="cta-actions">
            <a class="btn dark big" href="{{ route('contact') }}"><x-icon name="phone" :size="18" /> {{ __('Call :phone', ['phone' => config('company.phone')]) }}</a>
            <a class="btn light big" href="{{ company_mailto() }}"><x-icon name="mail" :size="18" /> {{ __('Email us') }}</a>
        </div>
    </section>
</main>

</x-layouts.app>
