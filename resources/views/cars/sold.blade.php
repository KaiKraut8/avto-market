<x-layouts.app :title="__('Mark as sold')" active="car" :back-to-cars="true">

<main class="wrap checkout-page">
    <div class="panel withdraw-box">
        <h1>{{ __('Sold :car elsewhere?', ['car' => $car->name]) }}</h1>
        <p>{{ __('If the car was sold without "Buy this car", enter the price it sold for. The car leaves the site and you pay the :rate% commission now.', ['rate' => $rate]) }}</p>
        @if ($errors->any())
            <ul class="form-errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('cars.sold', $car) }}">
            @csrf
            <div class="field">
                <label for="price">{{ __('Selling price') }}</label>
                <input type="text" id="price" name="price" inputmode="decimal" required value="{{ old('price', $car->price !== null ? number_format((float) $car->price, 0, ',', '.') : '') }}">
            </div>
            <label class="remember"><input type="checkbox" name="terms" value="1" required> {!! __('I have read <a href=":url" target="_blank">how buying works</a> and will pay the :rate% commission.', ['url' => route('how-buying'), 'rate' => $rate]) !!}</label>
            <div class="cancel-actions">
                <button type="submit" class="btn gold">{{ __('Mark as sold and pay the commission') }}</button>
                <a class="btn ghost" href="{{ route('cars.show', $car) }}">{{ __('Back') }}</a>
            </div>
        </form>
    </div>
</main>

</x-layouts.app>
