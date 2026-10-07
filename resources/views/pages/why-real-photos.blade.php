<x-layouts.app :title="__('Real photos, no stock images')" active="why">

<main class="wrap why-page">
    <div class="why-hero">
        <x-icon name="camera" :size="56" class="why-hero-icon" />
        <div>
            <p class="eyebrow">{{ __('Why buy here') }}</p>
            <h1>{{ __('Real photos,') }} <span>{{ __('no stock images.') }}</span></h1>
            <p class="lede">{{ __('Every photo is of the actual car for sale, taken by the seller. What you see is what is parked in the garage.') }}</p>
        </div>
    </div>

    <div class="stats why-stats">
        <div class="stat"><b>{{ $photos }}</b><span>{{ __('real photos') }}</span></div>
        <div class="stat"><b>{{ $withPhotos }} / {{ $cars }}</b><span>{{ __('cars with photos') }}</span></div>
    </div>

    @if ($gallery->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Latest photos') }}</h2>
            <div class="why-gallery">
                @foreach ($gallery as $photo)
                    <a href="{{ route('cars.show', $photo->car) }}" class="why-shot">
                        <img src="{{ $photo->url() }}" alt="{{ $photo->car->name }}" loading="lazy">
                        <span>{{ $photo->car->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="why-grid">
        <section class="panel">
            <h2>{{ __('What we check') }}</h2>
            <ul class="why-list">
                <li>{{ __('Photos are uploaded by the seller of that car, from their own account.') }}</li>
                <li>{{ __('Files are checked to be real images (JPG, PNG, WebP or GIF), not renamed documents.') }}</li>
                <li>{{ __('Big photos are resized, so they load fast on any phone.') }}</li>
            </ul>
        </section>
        <section class="panel">
            <h2>{{ __('Tip for buyers') }}</h2>
            <p>{{ __('Missing an angle? Use Contact seller on the car\'s page and ask for more photos before you visit.') }}</p>
        </section>
    </div>

    <div class="why-cta">
        <a class="btn accent big" href="{{ route('cars.index') }}">{{ __('Browse all cars') }} &rarr;</a>
        <a class="btn ghost big" href="{{ route('why', 'live-interest') }}">{{ __('Next: live interest') }}</a>
    </div>
</main>

</x-layouts.app>
