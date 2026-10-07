<x-layouts.app :title="__('Every part documented')" active="why">

<main class="wrap why-page">
    <div class="why-hero">
        <x-icon name="check" :size="56" class="why-hero-icon" />
        <div>
            <p class="eyebrow">{{ __('Why buy here') }}</p>
            <h1>{{ __('Every part') }} <span>{{ __('documented.') }}</span></h1>
            <p class="lede">{{ __("Each car lists the parts it comes with: steering wheel, brakes, pedals, seats, keys and more. No guessing, no surprises when you pick it up.") }}</p>
        </div>
    </div>

    <div class="stats why-stats">
        <div class="stat"><b>{{ $parts }}</b><span>{{ __('parts documented') }}</span></div>
        <div class="stat"><b>{{ $cars }}</b><span>{{ __('cars listed') }}</span></div>
        <div class="stat"><b>{{ $cars ? number_format($parts / $cars, 1, ',', '.') : 0 }}</b><span>{{ __('parts per car on average') }}</span></div>
    </div>

    <div class="why-grid">
        <section class="panel">
            <h2>{{ __('How it works') }}</h2>
            <ol class="why-steps">
                <li><b>{{ __('The seller lists the parts.') }}</b> {{ __('Each part gets a name and a short description, for example "Volan: leather, heated".') }}</li>
                <li><b>{{ __('Common parts are grouped.') }}</b> {{ __('Steering wheel, brakes, pedals, seats and keys are recognised, so cars are easy to compare.') }}</li>
                <li><b>{{ __('You see the list on every car.') }}</b> {{ __('On All cars as chips, and in full on the car\'s page.') }}</li>
            </ol>
        </section>

        <section class="panel">
            <h2>{{ __('Most documented parts') }}</h2>
            @if ($categories->isNotEmpty())
                <div class="chips why-chips">
                    @foreach ($categories as $c)
                        <span class="chip">{{ $c->name }} <b>{{ $c->n }}</b></span>
                    @endforeach
                </div>
            @else
                <p class="hint">{{ __('No parts listed yet.') }}</p>
            @endif
        </section>
    </div>

    @if ($example && $example->parts->isNotEmpty())
        <section class="panel why-example">
            <h2>{{ __('Example: :car', ['car' => $example->name]) }}</h2>
            <div class="table-scroll">
                <table class="parts">
                    <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Description') }}</th></tr></thead>
                    <tbody>
                        @foreach ($example->parts as $part)
                            <tr><td><strong>{{ $part->name }}</strong></td><td>{{ $part->description }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <a class="btn accent" href="{{ route('cars.show', $example) }}">{{ __('See this car') }} &rarr;</a>
        </section>
    @endif

    <div class="why-cta">
        <a class="btn accent big" href="{{ route('cars.index') }}">{{ __('Browse all cars') }} &rarr;</a>
        <a class="btn ghost big" href="{{ route('why', 'real-photos') }}">{{ __('Next: real photos') }}</a>
    </div>
</main>

</x-layouts.app>
