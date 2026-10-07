<x-layouts.app :title="$car->name" active="car" :back-to-cars="true">

<main class="wrap" data-watch-url="{{ route('cars.watch', $car) }}" data-leave-url="{{ route('cars.leave', $car) }}">
    <p class="crumbs"><a href="{{ route('home') }}">{{ __('Home') }}</a> &rsaquo; <a href="{{ route('cars.index') }}">{{ __('All cars') }}</a> &rsaquo; {{ $car->name }}</p>

    <div class="detail-head">
        <h1>{{ $car->name }}@if ($car->isSold()) <span class="sale-tag sold">{{ __('Sold') }}</span>@elseif ($car->isReserved()) <span class="sale-tag">{{ __('Reserved') }}</span>@endif @if ($car->isPremium()) <span class="premium-tag"><span aria-hidden="true">&#9813;</span> {{ __('Premium') }}</span>@endif</h1>
        <div class="detail-actions">
            <x-heart :car="$car" :wished="$wished" :label="true" />
            @unless ($car->isSold())
                <a class="btn accent" href="#contact"><x-icon name="chat" :size="17" /> {{ __('Contact seller') }}</a>
            @endunless
            <x-deal-price :car="$car" size="big" />
        </div>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    <div class="detail">
        <div>
            <div class="panel">
                @if ($car->photos->isNotEmpty())
                    <img class="hero-img" id="hero-img" src="{{ $car->photos->first()->url() }}" alt="{{ $car->name }}">
                @else
                    <div class="hero">{{ $car->initial() }}</div>
                @endif
                @if ($car->photos->count() > 1)
                    <div class="thumbs">
                        @foreach ($car->photos as $photo)
                            <img src="{{ $photo->url() }}" alt="" loading="lazy">
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="panel">
                <h2>{{ __('About this car') }}</h2>
                @if ($car->description)
                    <div class="description">{!! nl2br(e($car->description)) !!}</div>
                @else
                    <p class="hint">{{ __('No description yet.') }} @if ($canEdit){{ __('Write one in the panel on the right and press Save.') }}@endif</p>
                @endif
            </div>

            <div class="panel">
                <h2>{{ __('Parts (:count)', ['count' => $car->parts->count()]) }}</h2>
                <div class="table-scroll">
                    <table class="parts">
                        <thead>
                            <tr><th>{{ __('Part') }}</th><th>{{ __('Description') }}</th><th>{{ __('Added') }}</th>@if ($canEdit)<th></th>@endif</tr>
                        </thead>
                        <tbody>
                            @forelse ($car->parts as $part)
                                <tr>
                                    <td><strong>{{ $part->name }}</strong></td>
                                    <td>{{ $part->description }}</td>
                                    <td>{{ $part->created_at?->format('Y-m-d H:i') }}</td>
                                    @if ($canEdit)
                                        <td>
                                            <form method="post" action="{{ route('cars.parts.destroy', [$car, $part]) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn danger small">{{ __('Remove') }}</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="4">{{ __('No parts yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($canEdit)
                <div class="panel">
                    <h2>{{ __('Photos (:count)', ['count' => $car->photos->count()]) }}</h2>
                    @if ($car->photos->isNotEmpty())
                        <div class="photo-grid">
                            @foreach ($car->photos as $photo)
                                <figure>
                                    <img src="{{ $photo->url() }}" alt="" loading="lazy">
                                    <form method="post" action="{{ route('cars.photos.destroy', [$car, $photo]) }}" data-confirm="{{ __('Remove this photo?') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn danger small">{{ __('Remove') }}</button>
                                    </form>
                                </figure>
                            @endforeach
                        </div>
                    @endif
                    <form method="post" action="{{ route('cars.photos.store', $car) }}" enctype="multipart/form-data" id="photo-form" data-shrink-form>
                        @csrf
                        <div class="field">
                            <label for="photos">{{ __('Add photos') }}</label>
                            <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
                        </div>
                        <button type="submit" class="btn">{{ __('Upload') }}</button>
                    </form>
                </div>

                <div class="panel">
                    <h2>{{ __('Add a part') }}</h2>
                    <form method="post" action="{{ route('cars.parts.store', $car) }}">
                        @csrf
                        <div class="row">
                            <div>
                                <label for="part_name">{{ __('Part') }}</label>
                                <input type="text" id="part_name" name="name" maxlength="100" list="part-options" required>
                                <datalist id="part-options">
                                    @foreach ($categories as $option)
                                        <option value="{{ $option }}">
                                    @endforeach
                                </datalist>
                            </div>
                            <div>
                                <label for="part_description">{{ __('Description') }}</label>
                                <input type="text" id="part_description" name="description" maxlength="1000">
                            </div>
                            <button type="submit" class="btn">{{ __('Add part') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <aside>
            @php
                $sale = $car->activeSale;
                $rate = \App\Models\CarSale::rate();
                $commission = $car->price !== null ? \App\Models\CarSale::commissionFor((float) $car->price) : null;
            @endphp
            @if ($car->isSold())
                <div class="panel sale-panel sold">
                    <h2>{{ __('Sold') }}</h2>
                    <p>{{ __('This car was sold on :date.', ['date' => $car->sold_at->format('Y-m-d')]) }}</p>
                    <a class="btn ghost" href="{{ route('cars.index') }}">{{ __('See other cars') }}</a>
                </div>
            @elseif ($car->isReserved() && $canEdit)
                <div class="panel sale-panel">
                    <h2>{{ __('Reserved: a buyer paid') }}</h2>
                    <p>{{ __(':name paid the :rate% commission online. Arrange the handover; at the handover they pay you :remainder.', ['name' => $sale->buyer?->name, 'rate' => (float) $sale->rate, 'remainder' => \App\Support\Money::price($sale->remainder())]) }}</p>
                    @if ($sale->buyer)
                        <a class="seller-line" href="tel:{{ preg_replace('/[^+0-9]/', '', $sale->buyer->phone) }}"><span>{{ __('Phone') }}</span><b>{{ $sale->buyer->phone }}</b></a>
                        <a class="seller-line" href="mailto:{{ $sale->buyer->email }}"><span>{{ __('Email') }}</span><b>{{ $sale->buyer->email }}</b></a>
                    @endif
                    <div class="sale-actions">
                        <form method="post" action="{{ route('sales.complete', $sale) }}" data-confirm="{{ __('Has the buyer paid you and taken the car?') }}">
                            @csrf
                            <button type="submit" class="btn gold">{{ __('Confirm the sale') }}</button>
                        </form>
                        <form method="post" action="{{ route('sales.cancel', $sale) }}" data-confirm="{{ __('Cancel the sale? The buyer gets their money back and the car is for sale again.') }}">
                            @csrf
                            <button type="submit" class="btn danger">{{ __('Cancel the sale') }}</button>
                        </form>
                    </div>
                </div>
            @elseif ($car->isReserved() && auth()->id() && (int) $sale->buyer_id === (int) auth()->id())
                <div class="panel sale-panel mine">
                    <h2>{{ __('Reserved for you') }}</h2>
                    <p>{{ __('You paid the :rate% commission. The seller will contact you; at the handover you pay them :remainder.', ['rate' => (float) $sale->rate, 'remainder' => \App\Support\Money::price($sale->remainder())]) }}</p>
                    @if ($contact)
                        <a class="seller-line" href="tel:{{ preg_replace('/[^+0-9]/', '', $contact['phone']) }}"><span>{{ __('Phone') }}</span><b>{{ $contact['phone'] }}</b></a>
                        <a class="seller-line" href="mailto:{{ $contact['email'] }}"><span>{{ __('Email') }}</span><b>{{ $contact['email'] }}</b></a>
                    @endif
                </div>
            @elseif ($car->isReserved())
                <div class="panel sale-panel">
                    <h2>{{ __('Reserved') }}</h2>
                    <p>{{ __('Another buyer has reserved this car. If the sale falls through, it will be for sale again.') }}</p>
                </div>
            @elseif ($car->isBuyable() && ! $isOwner)
                <div class="panel sale-panel buy">
                    <h2>{{ __('Buy this car') }}</h2>
                    <div class="calc-rows small">
                        <div><span>{{ __('Price of the car') }}</span><b>@price($car->price)</b></div>
                        <div class="calc-online"><span>{{ __('You pay online now (:rate%, to KAI Garage)', ['rate' => $rate]) }}</span><b>@eur($commission)</b></div>
                        <div><span>{{ __('You pay the seller at the handover') }}</span><b>@price((float) $car->price - $commission)</b></div>
                    </div>
                    <a class="btn gold big buy-btn" href="{{ route('checkout.create', ['product' => 'reserve', 'car' => $car->id]) }}">{{ __('Buy this car') }}</a>
                    <p class="hint">{{ __('Paying online reserves the car for you. Refunded in full if the seller cancels.') }} <a href="{{ route('how-buying') }}">{{ __('How buying works') }}</a></p>
                </div>
            @endif

            @if ($deal = $car->activeDeal)
                <div class="panel deal-panel">
                    <h2><x-icon name="tag" :size="16" /> {{ __('Special deal') }}</h2>
                    <p class="deal-panel-price">
                        <s>@price($deal->regular_price)</s>
                        <b>@price($deal->deal_price)</b>
                        <span class="deal-off">&minus;{{ $deal->percentOff() }}%</span>
                    </p>
                    @if ($deal->member_price !== null)
                        <p class="deal-member">
                            &#9813; {{ __('Premium buyers pay :price', ['price' => \App\Support\Money::price($deal->member_price)]) }}
                            @if (auth()->user()?->hasBuyerPremium())
                                <span class="gold">{{ __('That is you: mention it when you contact the seller, they will see it too.') }}</span>
                            @else
                                <a href="{{ route('premium.index') }}#buyers">{{ __('Become a premium buyer') }}</a>
                            @endif
                        </p>
                    @endif
                    <p class="hint"><x-icon name="clock" :size="14" /> {{ __('Ends :date', ['date' => $deal->ends_at->format('Y-m-d H:i')]) }}</p>
                    @if ($canEdit)
                        <form method="post" action="{{ route('cars.deal.destroy', $car) }}" data-confirm="{{ __('End this deal now?') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn ghost small">{{ __('End the deal') }}</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($canDeal)
                <div class="panel upsell deal-form" id="deal">
                    <span class="upsell-crown" aria-hidden="true">&#9813;</span>
                    <h2>{{ $car->activeDeal ? __('Replace the deal') : __('Run a special deal') }}</h2>
                    <p>{{ __('Cut the price for a few days. The car gets a Special deal badge, appears on the Deals page and the home page, and buyers who saved it or similar cars get an alert.') }}</p>
                    @if ($errors->deal->any())
                        <ul class="form-errors">
                            @foreach ($errors->deal->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <form method="post" action="{{ route('cars.deal.store', $car) }}">
                        @csrf
                        <div class="field">
                            <label for="deal_price">{{ __('Deal price (now :price)', ['price' => \App\Support\Money::price($car->price)]) }}</label>
                            <input type="text" id="deal_price" name="deal_price" inputmode="decimal" required value="{{ old('deal_price') }}" placeholder="{{ number_format((float) $car->price * 0.93, 0, ',', '.') }}">
                        </div>
                        <div class="field">
                            <label for="member_price">{{ __('Member price for premium buyers (optional)') }}</label>
                            <input type="text" id="member_price" name="member_price" inputmode="decimal" value="{{ old('member_price') }}" placeholder="{{ number_format((float) $car->price * 0.9, 0, ',', '.') }}">
                        </div>
                        <div class="field">
                            <label for="days">{{ __('How long') }}</label>
                            <select id="days" name="days">
                                @foreach (\App\Models\CarDeal::DURATIONS as $days)
                                    <option value="{{ $days }}" @selected((int) old('days', 7) === $days)>{{ trans_choice(':count day|:count days', $days) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn gold"><x-icon name="tag" :size="16" /> {{ $car->activeDeal ? __('Replace the deal') : __('Start the deal') }}</button>
                    </form>
                </div>
            @elseif ($isOwner && ! auth()->user()->hasPremium() && ! $car->isSold())
                <div class="panel upsell">
                    <span class="upsell-crown" aria-hidden="true">&#9813;</span>
                    <h2>{{ __('Special deals') }}</h2>
                    <p>{{ __('Premium sellers can cut the price for a few days and buyers who saved this car or similar ones are told right away.') }}</p>
                    <a class="btn gold" href="{{ route('premium.index') }}">&#9813; {{ __('See premium plans') }}</a>
                </div>
            @endif

            @unless ($car->isSold())
            <div class="panel contact-panel" id="contact">
                <h2>{{ __('Contact seller') }}</h2>
                @if ($isOwner)
                    <p class="hint">{{ __('This is your listing. Buyers who contact you see the name, phone and email from your profile.') }} <a href="{{ route('account') }}">{{ __('Your profile') }}</a></p>
                @elseif (! $contact)
                    <p class="hint">{{ __("The seller hasn't added contact details yet. Once they do, you can reach them here.") }}</p>
                @else
                    <div class="seller-head">
                        <span class="seller-avatar">{{ mb_strtoupper(mb_substr($contact['name'], 0, 1)) }}</span>
                        <span>
                            <b>{{ $contact['name'] }}</b>
                            @if ($car->locationLabel())
                                <small>{{ $car->locationLabel() }}</small>
                            @endif
                        </span>
                    </div>
                    @if ($contacted)
                        @if (session('contacted'))
                            <div class="notice">&#10003; {{ __('Your details were sent. You can now reach the seller directly.') }}</div>
                        @endif
                        <a class="seller-line" href="tel:{{ preg_replace('/[^+0-9]/', '', $contact['phone']) }}">
                            <span>{{ __('Phone') }}</span><b>{{ $contact['phone'] }}</b>
                        </a>
                        <a class="seller-line" href="mailto:{{ $contact['email'] }}?subject={{ rawurlencode(__('About your :car', ['car' => $car->name])) }}">
                            <span>{{ __('Email') }}</span><b>{{ $contact['email'] }}</b>
                        </a>
                    @else
                        <p class="hint">{{ __("Enter accurate contact details of your own and the seller's phone and email are shown right away.") }}</p>
                        @if ($errors->inquiry->any())
                            <ul class="form-errors">
                                @foreach ($errors->inquiry->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <form method="post" action="{{ route('cars.inquiries.store', $car) }}" class="contact-form">
                            @csrf
                            <div class="field">
                                <label for="buyer_name">{{ __('Your name') }}</label>
                                <input type="text" id="buyer_name" name="buyer_name" maxlength="60" required autocomplete="name" value="{{ old('buyer_name') }}">
                            </div>
                            <div class="field">
                                <label for="buyer_email">{{ __('Email') }}</label>
                                <input type="email" id="buyer_email" name="buyer_email" maxlength="120" required autocomplete="email" value="{{ old('buyer_email') }}">
                            </div>
                            <div class="field">
                                <label for="buyer_phone">{{ __('Phone') }}</label>
                                <input type="tel" id="buyer_phone" name="buyer_phone" maxlength="25" required autocomplete="tel" placeholder="+386 40 123 456" value="{{ old('buyer_phone') }}">
                            </div>
                            <div class="field">
                                <label for="buyer_message">{{ __('Message (optional)') }}</label>
                                <textarea id="buyer_message" name="buyer_message" rows="3" maxlength="2000">{{ old('buyer_message', __('Hi, is the :car still available?', ['car' => $car->name])) }}</textarea>
                            </div>
                            <button type="submit" class="btn accent">{{ __('Contact seller') }}</button>
                        </form>
                    @endif
                @endif
            </div>

            @endunless

            <div class="panel">
                <h2>{{ __('Car data') }}</h2>
                <table class="specs">
                    <tr><th>{{ __('ID') }}</th><td>#{{ $car->id }}</td></tr>
                    <tr><th>{{ __('Price') }}</th><td>@price($car->price)@if ($car->activeDeal) <span class="hint">({{ __('deal: :price', ['price' => \App\Support\Money::price($car->activeDeal->deal_price)]) }})</span>@endif</td></tr>
                    <tr><th>{{ __('Location') }}</th><td>{!! $car->locationLabel() ? e($car->locationLabel()) : '<span class="hint">'.e(__('Not set')).'</span>' !!}</td></tr>
                    <tr><th>{{ __('Added') }}</th><td>{{ $car->created_at?->format('Y-m-d H:i') }}</td></tr>
                    <tr><th>{{ __('Parts') }}</th><td>{{ $car->parts->count() }}</td></tr>
                    <tr><th>{{ __('Seen by') }}</th><td>{{ trans_choice(':count person|:count people', $stats['people']) }} <span class="hint">({{ trans_choice(':count view|:count views', $stats['total']) }})</span></td></tr>
                    <tr><th>{{ __('Watching now') }}</th><td><span class="live-dot"></span><span id="watching-now">{{ $stats['watching'] }}</span></td></tr>
                </table>
                @if ($canEdit)
                    @include('cars._form')
                @endif
            </div>

            @if ($canEdit && ! $car->isPremium() && ! $car->isSold())
                <div class="panel upsell">
                    <span class="upsell-crown" aria-hidden="true">&#9813;</span>
                    <h2>{{ __('Push this car forward') }}</h2>
                    <div class="upsell-option">
                        @if ($car->isBoosted())
                            <p><b>&#8679; {{ __('Pushed until :date', ['date' => $car->boosted_until->format('Y-m-d H:i')]) }}</b>. {{ __('Shown first among the regular cars.') }}</p>
                        @else
                            <p>{{ __('Show it first among the regular cars for a week.') }}</p>
                        @endif
                        <form method="post" action="{{ route('cars.boost', $car) }}">
                            @csrf
                            <button type="submit" class="btn details">{{ $car->isBoosted() ? __('Add another week') : __('Push forward') }} &middot; @eur(\App\Services\Pricing::boostWeekly()) / {{ __('week') }}</button>
                        </form>
                    </div>
                    <div class="upsell-option">
                        <p>{!! __('Or make your <b>account premium</b>: every car you list goes to the top, in gold.') !!}</p>
                        <a class="btn gold" href="{{ route('premium.index') }}">&#9813; {{ __('See premium plans') }}</a>
                    </div>
                    <p class="hint demo-note">{{ __('Paid by card, PayPal or paysafecard on the next page.') }}</p>
                </div>
            @endif

            @if ($canEdit)
                @unless ($car->isSold() || $car->isReserved())
                    <div class="panel">
                        <h2>{{ __('Sold the car another way?') }}</h2>
                        <p class="hint">{{ __('Mark it as sold and pay the :rate% commission. It leaves the site.', ['rate' => \App\Models\CarSale::rate()]) }}</p>
                        <a class="btn ghost" href="{{ route('cars.sold.create', $car) }}">{{ __('Mark it as sold') }}</a>
                    </div>
                @endunless
                <div class="panel">
                    <h2>{{ __('Remove') }}</h2>
                    <p class="hint">{{ __('The car is hidden from the site but stays in the database.') }}</p>
                    <form method="post" action="{{ route('cars.destroy', $car) }}" data-confirm="{{ __('Delete this car? It stays in the database.') }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn danger">{{ __('Delete car') }}</button>
                    </form>
                </div>
            @endif
        </aside>
    </div>
</main>

</x-layouts.app>
