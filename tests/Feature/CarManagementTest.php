<?php

use App\Models\Car;
use App\Models\CarLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

function carData(array $overrides = []): array
{
    return array_merge(['name' => 'Škoda Octavia', 'price' => '12.900', 'location' => 'Maribor', 'country' => 'Slovenia', 'description' => "Line one\nLine two", 'year' => 2019, 'terms' => 1], $overrides);
}

it('saves the year and rejects an impossible one', function () {
    $seller = User::factory()->create();
    $this->actingAs($seller)->post('/cars', carData(['year' => 1800]))->assertSessionHasErrors('year');
    $this->actingAs($seller)->post('/cars', carData(['year' => 'soon']))->assertSessionHasErrors('year');
    $this->actingAs($seller)->post('/cars', carData(['year' => 2019]));
    expect(Car::first()->year)->toBe(2019);
    $this->get('/cars/'.Car::first()->id)->assertSee('2019');
});

it('needs an account to post a car', function () {
    $this->get('/cars/create')->assertRedirect('/login');
    $this->post('/cars', carData())->assertRedirect('/login');
    expect(Car::count())->toBe(0);
});

it('posts a car with photos and requires location and country', function () {
    $seller = User::factory()->create();

    $this->actingAs($seller)->post('/cars', carData(['location' => '', 'country' => 'Atlantis']))
        ->assertSessionHasErrors(['location', 'country']);

    $response = $this->actingAs($seller)->post('/cars', carData(['photos' => [
        UploadedFile::fake()->image('front.jpg', 3000, 2000),
        UploadedFile::fake()->create('evil.png', 10, 'image/png'),   // not a real image
    ]]));

    $car = Car::firstOrFail();
    $response->assertRedirect(route('cars.show', $car));
    expect($car->user_id)->toBe($seller->id)
        ->and((float) $car->price)->toBe(12900.0)
        ->and($car->photos()->count())->toBe(1);
    $photo = $car->photos()->first();
    Storage::disk('public')->assertExists($photo->path);
    expect(getimagesizefromstring(Storage::disk('public')->get($photo->path))[0])->toBe(1600);   // scaled down
});

it('sends a paid listing choice to the checkout after saving the car', function () {
    $seller = User::factory()->create();

    $this->actingAs($seller)->post('/cars', carData(['plan' => 'boost']))
        ->assertRedirect(route('checkout.create', ['product' => 'boost', 'car' => Car::first()->id]));
    expect(Car::first()->isBoosted())->toBeFalse();   // only once it is paid

    $this->actingAs($seller)->post('/cars', carData(['plan' => 'premium', 'billing' => 'yearly']))
        ->assertRedirect(route('checkout.create', ['product' => 'seller', 'billing' => 'yearly']));
    expect($seller->fresh()->hasPremium())->toBeFalse();
});

it('lets only the seller change a car', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $car = Car::factory()->for($owner)->create(['name' => 'Original']);

    $this->actingAs($other)->put(route('cars.update', $car), carData(['name' => 'Hacked']))->assertForbidden();
    $this->actingAs($other)->delete(route('cars.destroy', $car))->assertForbidden();
    $this->actingAs($other)->post(route('cars.boost', $car))->assertForbidden();
    $this->actingAs($other)->post(route('cars.photos.store', $car), ['photos' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
    expect($car->fresh()->name)->toBe('Original');

    $this->actingAs($owner)->put(route('cars.update', $car), carData(['name' => 'Renamed']))->assertRedirect(route('cars.show', $car));
    expect($car->fresh()->name)->toBe('Renamed')
        ->and(CarLog::where('car_id', $car->id)->where('action', 'edit')->exists())->toBeTrue();
});

it('shows edit controls only to the seller', function () {
    $owner = User::factory()->create();
    $car = Car::factory()->for($owner)->create();

    $this->get(route('cars.show', $car))->assertOk()->assertDontSee('class="car-form"', false)->assertDontSee('Delete car');
    $this->actingAs(User::factory()->create())->get(route('cars.show', $car))->assertDontSee('class="car-form"', false);
    $this->actingAs($owner)->get(route('cars.show', $car))->assertSee('class="car-form"', false)->assertSee('Delete car')->assertSee('This is your listing');
});

it('soft deletes a car and logs it', function () {
    $owner = User::factory()->create();
    $car = Car::factory()->for($owner)->create();

    $this->actingAs($owner)->delete(route('cars.destroy', $car))->assertRedirect('/');

    expect(Car::find($car->id))->toBeNull()
        ->and(Car::withTrashed()->find($car->id))->not->toBeNull()
        ->and(CarLog::where('car_id', $car->id)->where('action', 'delete')->exists())->toBeTrue();
    $this->get(route('cars.show', $car))->assertNotFound();
});

it('sends a push forward to the checkout, but not for a premium seller\'s car', function () {
    $owner = User::factory()->create();
    $car = Car::factory()->for($owner)->create();
    $this->actingAs($owner)->post(route('cars.boost', $car))->assertRedirect(route('checkout.create', ['product' => 'boost', 'car' => $car->id]));

    $premium = User::factory()->premium()->create();
    $gold = Car::factory()->for($premium)->create();
    $this->actingAs($premium)->post(route('cars.boost', $gold))->assertRedirect(route('cars.show', $gold));
    $this->actingAs($premium)->get(route('checkout.create', ['product' => 'boost', 'car' => $gold->id]))->assertRedirect(route('cars.show', $gold));
});

// CSRF protection can't be tested here: Laravel switches it off while tests run. It is checked against the running site instead.
