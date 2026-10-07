<x-layouts.app :title="__('Contact us')" active="contact">

<main class="wrap contact-page">
    <div class="contact-head">
        <p class="eyebrow">{{ __('Talk to a person') }}</p>
        <h1>{{ __('Call us,') }} <span>{{ __("we're happy to help.") }}</span></h1>
        <p class="lede">{{ __('Questions about a car, a test drive or selling your own? Call, write or drop by the garage.') }}</p>
    </div>

    <div class="contact-grid">
        <section class="panel call-card">
            <div @class(['open-status', 'open' => $openNow])>
                <span class="live-dot @unless ($openNow) idle @endunless"></span>
                @if ($openNow)
                    {{ __('Open now · until :time', ['time' => $closesAt]) }}
                @elseif ($next)
                    {{ __('Closed now · opens :day at :time', ['day' => $next['day'], 'time' => $next['time']]) }}
                @else
                    {{ __('Closed now') }}
                @endif
            </div>
            <x-icon name="phone" :size="44" class="call-icon" />
            <a class="call-number" href="{{ company_phone_href() }}">{{ config('company.phone') }}</a>
            <div class="call-actions">
                <a class="btn gold big" href="{{ company_phone_href() }}"><x-icon name="phone" :size="18" /> {{ __('Call now') }}</a>
                <button type="button" class="btn ghost big" data-copy="{{ config('company.phone') }}">{{ __('Copy number') }}</button>
            </div>
            <p class="hint">{{ __('On a computer? The Call button opens your phone app or calling software. You can also copy the number.') }}</p>
            <div class="call-tip">
                <b>{{ __('Calling about a car?') }}</b>
                {{ __('Have its number ready: it is shown on every car as #ID, for example #2.') }}
            </div>
        </section>

        <div class="contact-side">
            <section class="panel">
                <h2><x-icon name="mail" :size="18" /> {{ __('Email') }}</h2>
                <a class="contact-big" href="{{ company_mailto() }}">{{ config('company.email') }}</a>
                <div class="contact-actions">
                    <a class="btn accent" href="{{ company_mailto() }}">{{ __('Write an email') }}</a>
                    <a class="btn ghost" href="{{ company_gmail_url() }}" target="_blank" rel="noopener">{{ __('Open in Gmail') }}</a>
                    <button type="button" class="btn ghost" data-copy="{{ config('company.email') }}">{{ __('Copy address') }}</button>
                </div>
                <p class="hint">{{ __('"Write an email" opens your mail app with our address already filled in as the recipient.') }}</p>
            </section>

            <section class="panel">
                <h2><x-icon name="pin" :size="18" /> {{ __('Visit the garage') }}</h2>
                <p class="contact-address">{{ config('company.address') }}</p>
                <a class="btn ghost" href="{{ config('company.map_url') }}" target="_blank" rel="noopener">{{ __('Open in maps') }} &rarr;</a>
                <h3 class="hours-title"><x-icon name="clock" :size="16" /> {{ __('Opening hours') }}</h3>
                <table class="hours">
                    @foreach ($week as $row)
                        <tr @class(['today' => $row['today']])>
                            <th>{{ $row['day'] }}@if ($row['today']) <span class="today-tag">{{ __('today') }}</span>@endif</th>
                            <td>{{ $row['hours'] ?? __('Closed') }}</td>
                        </tr>
                    @endforeach
                </table>
            </section>
        </div>
    </div>
</main>

</x-layouts.app>
