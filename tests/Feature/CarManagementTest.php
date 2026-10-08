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
    expect($photo->mime)->toBe('image/jpeg');   // stored in the database, not as a file
    Storage::disk('public')->assertDirectoryEmpty('/');

    $image = $this->get(route('photos.show', $photo))->assertOk()->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    expect(getimagesizefromstring($image->getContent())[0])->toBe(1600);   // scaled down
    $this->get(route('photos.show', $photo), ['If-None-Match' => '"photo-'.$photo->id.'"'])->assertStatus(304);
});

it('keeps two sellers\' photos apart even when the files have the same name', function () {
    [$ana, $bor] = User::factory()->count(2)->create();
    $carA = Car::factory()->for($ana)->create();
    $carB = Car::factory()->for($bor)->create();

    $this->actingAs($ana)->post(route('cars.photos.store', $carA), ['photos' => [UploadedFile::fake()->image('car.jpg', 800, 600)->size(100)]]);
    $this->actingAs($bor)->post(route('cars.photos.store', $carB), ['photos' => [UploadedFile::fake()->image('car.jpg', 400, 300)]]);

    $a = $carA->photos()->first();
    $b = $carB->photos()->first();
    expect($a->id)->not->toBe($b->id)
        ->and(getimagesizefromstring($this->get($a->url())->getContent())[0])->toBe(800)
        ->and(getimagesizefromstring($this->get($b->url())->getContent())[0])->toBe(400);

    // only the seller may add photos to a car, and a guest none at all
    $this->actingAs($bor)->post(route('cars.photos.store', $carA), ['photos' => [UploadedFile::fake()->image('x.jpg')]])->assertForbidden();
    auth()->logout();
    $this->post(route('cars.photos.store', $carA), ['photos' => [UploadedFile::fake()->image('x.jpg')]])->assertRedirect('/login');
    expect($carA->photos()->count())->toBe(1);

    // a deleted photo is gone from the database
    $this->actingAs($ana)->delete(route('cars.photos.destroy', [$carA, $a]));
    $this->get(route('photos.show', $a->id))->assertNotFound();
});

it('imports photos from a folder by car name, without adding the same picture twice', function () {
    $dir = storage_path('framework/testing/inbox');
    @mkdir($dir, 0777, true);
    array_map('unlink', glob($dir.'/*') ?: []);
    $car = Car::factory()->create(['name' => 'Škoda Octavia']);
    $bmw = Car::factory()->create(['name' => 'BMW']);
    imagejpeg(imagecreatetruecolor(300, 200), $dir.'/Skoda Octavia.jpg');
    $img = imagecreatetruecolor(300, 200);
    imagefilledrectangle($img, 0, 0, 150, 200, imagecolorallocate($img, 255, 255, 255));
    imagejpeg($img, $dir.'/bmw2.jpg');
    imagejpeg(imagecreatetruecolor(300, 200), $dir.'/Lamborghini.jpg');

    $this->artisan('photos:import', ['folder' => 'storage/framework/testing/inbox'])->assertSuccessful();
    expect($car->photos()->count())->toBe(1)->and($bmw->photos()->count())->toBe(1);

    $this->artisan('photos:import', ['folder' => 'storage/framework/testing/inbox'])->assertSuccessful();   // second run adds nothing
    expect($car->photos()->count())->toBe(1)->and($bmw->photos()->count())->toBe(1);
    array_map('unlink', glob($dir.'/*') ?: []);
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
