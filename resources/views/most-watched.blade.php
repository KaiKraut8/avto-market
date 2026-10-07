<x-layouts.app :title="__('Most watched')" active="most-watched">

<main class="wrap">
    <div class="page-title">
        <h1>{{ __('Most watched') }}</h1>
        <span class="updated">{{ __('Live · updates every 10 s') }} <span id="updated-at"></span></span>
    </div>

    <div class="stats" style="margin-bottom:1.5rem">
        <div class="stat"><b id="sum-people">{{ $cars->sum('people') }}</b><span>{{ __('people looked (per car)') }}</span></div>
        <div class="stat"><b id="sum-total">{{ $cars->sum('total') }}</b><span>{{ __('total views') }}</span></div>
        <div class="stat"><b id="sum-watching">{{ $cars->sum('watching') }}</b><span>{{ __('watching now') }}</span></div>
    </div>

    @if ($cars->isNotEmpty())
        <div class="table-scroll">
            <table class="views-table" data-stats-url="{{ route('most-watched.stats') }}">
                <thead>
                    <tr><th>#</th><th>{{ __('Car') }}</th><th>{{ __('People looked') }}</th><th></th><th>{{ __('Total views') }}</th><th>{{ __('Watching now') }}</th><th>{{ __('Last viewed') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($cars as $rank => $car)
                        <tr data-id="{{ $car->id }}">
                            <td><span class="rank rank-{{ $rank + 1 }}">{{ $rank + 1 }}</span></td>
                            <td>
                                <a class="car-mini" href="{{ route('cars.show', $car) }}">
                                    @if ($car->coverPhoto)
                                        <img class="mini-thumb" src="{{ $car->coverPhoto->url() }}" alt="" loading="lazy">
                                    @else
                                        <span class="mini-thumb">{{ $car->initial() }}</span>
                                    @endif
                                    {{ $car->name }}
                                </a>
                            </td>
                            <td><span class="num accent" data-f="people">{{ (int) $car->people }}</span></td>
                            <td><div class="bar"><span data-f="bar" style="width: {{ round((int) $car->people / $maxPeople * 100) }}%"></span></div></td>
                            <td><span class="num" data-f="total">{{ (int) $car->total }}</span></td>
                            <td class="watch-cell"><span @class(['live-dot', 'idle' => ! $car->watching]) data-f="dot"></span><span class="num" data-f="watching">{{ (int) $car->watching }}</span></td>
                            <td class="hint" data-f="last">{{ $car->last_viewed ? \Illuminate\Support\Carbon::parse($car->last_viewed)->format('Y-m-d H:i') : __('Never') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint" style="margin-top:1rem">{{ __('A person is counted once per car (by a browser cookie). "Watching now" is everyone who has the car\'s page open at this moment.') }}</p>
    @else
        <div class="empty">{{ __('No cars yet.') }}</div>
    @endif
</main>

</x-layouts.app>
