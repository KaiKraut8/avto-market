<x-layouts.app :title="__('Best car offers in town')" active="home">

<section class="home-hero" data-hero>
    <x-hero-scene />

    <div class="inner hero-grid">
        <div class="hero-copy">
            <p class="pill"><span class="live-dot"></span>{{ trans_choice(':count car in the garage right now|:count cars in the garage right now', $cars->count()) }}</p>
            <h1>{{ __('The best car offers') }} <span>{{ __('in town.') }}</span></h1>
            <p class="hero-lede">{{ __('Hand-picked cars, photographed and documented part by part. Fair prices, no surprises: find your next car before someone else does.') }}</p>
            <x-search-panel :bounds="$bounds" :hero="true" :placeholder="__('What car are you looking for? e.g. BMW, Mercedes, Ljubljana')" />
            <dl class="hero-facts">
                @if ($lowest !== null)
                    <div><dt>{{ __('Prices from') }}</dt><dd>@price($lowest)</dd></div>
                @endif
                @if ($bounds['yearMin'] && $bounds['yearMax'] > $bounds['yearMin'])
                    <div class="fact-teal"><dt>{{ __('Years') }}</dt><dd>{{ $bounds['yearMin'] }}–{{ $bounds['yearMax'] }}</dd></div>
                @endif
                <div class="fact-blue"><dt>{{ __('Parts documented') }}</dt><dd>{{ $totalParts }}</dd></div>
                @if ($interested > 0)
                    <div class="fact-violet"><dt>{{ __('Interested buyers') }}</dt><dd>{{ $interested }}</dd></div>
                @endif
            </dl>
        </div>

        @if ($topPick)
            {{-- the featured car floats and can be turned around by dragging; the back shows its key facts --}}
            <div class="hero-car-stage">
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
                                <div><dt>{{ __('Parts documented') }}</dt><dd>{{ $topPick->parts_count }}</dd></div>
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
                @php($parts = $car->parts->take(3))
                <a @class(['offer', 'premium' => $car->isPremium()]) href="{{ route('cars.show', $car) }}" style="--i: {{ $i }}">
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
                        @if ($parts->isNotEmpty())
                            <div class="chips">
                                @foreach ($parts as $part)
                                    <span class="chip">{{ $part->name }}</span>
                                @endforeach
                                @if ($car->parts_count > $parts->count())
                                    <span class="chip more">+{{ $car->parts_count - $parts->count() }}</span>
                                @endif
                            </div>
                        @endif
                        <span class="offer-go">{{ __('View this car') }} <span aria-hidden="true">&rarr;</span></span>
                    </div>
                </a>
            @endforeach
        </section>
    @else
        <div class="empty">{{ __('The garage is empty.') }} <a href="{{ route('cars.create') }}">{{ __('Add the first car') }}</a>.</div>
    @endif

    @if ($deals->isNotEmpty())
        <div class="section-head" id="deals">
            <div>
                <p class="eyebrow rose">{{ __('For a few days only') }}</p>
                <h2 class="big-title">{{ __('Special deals') }}</h2>
            </div>
            <a href="{{ route('deals.index') }}">{{ __('All deals') }} &rarr;</a>
        </div>
        <section class="deal-strip">
            @foreach ($deals as $car)
                <a class="deal-tile" href="{{ route('cars.show', $car) }}">
                    <div class="deal-tile-photo">
                        <x-car-photo :car="$car" />
                        <span class="deal-off">&minus;{{ $car->activeDeal->percentOff() }}%</span>
                        @if ($car->for_you)
                            <span class="for-you"><x-icon name="heart" :size="12" /> {{ __('For you') }}</span>
                        @endif
                    </div>
                    <div class="deal-tile-body">
                        <b>{{ $car->name }}</b>
                        <x-deal-price :car="$car" size="tile" />
                        <small><x-icon name="clock" :size="13" /> {{ __('Ends :date', ['date' => $car->activeDeal->ends_at->format('Y-m-d')]) }}</small>
                    </div>
                </a>
            @endforeach
        </section>
    @endif

    <section class="premium-promo">
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
        <a class="why-item teal" href="{{ route('why', 'documented-parts') }}">
            <x-icon name="check" />
            <h3>{{ __('Every part documented') }}</h3>
            <p>{{ __("Steering, brakes, seats, keys: each car lists what it has, so you know exactly what you're buying.") }}</p>
            <span class="why-more">{{ __('Learn more') }} &rarr;</span>
        </a>
        <a class="why-item blue" href="{{ route('why', 'real-photos') }}">
            <x-icon name="camera" />
            <h3>{{ __('Real photos, no stock images') }}</h3>
            <p>{{ __('What you see is the actual car sitting in our garage, not a picture from a catalogue.') }}</p>
            <span class="why-more">{{ __('Learn more') }} &rarr;</span>
        </a>
        <a class="why-item violet" href="{{ route('why', 'live-interest') }}">
            <x-icon name="eye" />
            <h3>{{ __('See the interest live') }}</h3>
            <p>{{ __("Every car shows how many people looked at it and who's viewing it right now. Good offers go fast.") }}</p>
            <span class="why-more">{{ __('Learn more') }} &rarr;</span>
        </a>
    </section>

    <section class="cta-band">
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
