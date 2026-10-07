<?php

use App\Models\Car;
use App\Models\User;
use App\Support\Visitor;

it('lists premium cars first, then pushed, then the rest under "Other options"', function () {
    Car::factory()->create(['name' => 'Plain Car']);
    Car::factory()->boosted()->create(['name' => 'Pushed Car']);
    Car::factory()->for(User::factory()->premium())->create(['name' => 'Gold Car']);

    $this->get('/cars')->assertOk()
        ->assertSeeInOrder(['Premium listings', 'Gold Car', 'Other options', 'Pushed Car', 'Plain Car']);
});

it('searches and falls back to other options when nothing matches', function () {
    Car::factory()->create(['name' => 'BMW 320d']);
    Car::factory()->create(['name' => 'Audi A4']);

    $this->get('/cars?q=bmw')->assertSee('BMW 320d')->assertDontSee('Audi A4');
    $this->get('/cars?q=xyzzy')->assertSee('No cars match')->assertSee('Other options you might like')->assertSee('Audi A4');
    $this->get('/cars?q='.urlencode('%'))->assertSee('No cars match');   // % is literal text, not "match all"
});

it('shows premium cars as home page highlights, filled up to three', function () {
    Car::factory()->create(['name' => 'Third Car']);
    Car::factory()->boosted()->create(['name' => 'Pushed Car']);
    Car::factory()->for(User::factory()->premium())->create(['name' => 'Gold Car']);

    $this->get('/')->assertOk()
        ->assertSee('Premium highlights')
        ->assertSeeInOrder(['Gold Car', 'Pushed Car', 'Third Car']);
});

it('serves the information pages', function () {
    Car::factory()->create();
    foreach (['/contact', '/why/documented-parts', '/why/real-photos', '/why/live-interest', '/premium', '/most-watched', '/wishlist', '/login', '/register', '/forgot-password'] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get('/why/nope')->assertNotFound();
    $this->get('/contact')->assertSee('tel:+38640123456', false)->assertSee('mailto:kaigarage.info@gmail.com', false);
});

it('serves the privacy and cookie policies, linked from every page', function () {
    $this->get('/privacy')->assertOk()->assertSee('Privacy policy')->assertSee(config('company.name'))->assertSee('Mollie B.V., Amsterdam');
    $this->get('/cookies')->assertOk()->assertSee('Cookie policy')
        ->assertSee(config('session.cookie'))->assertSee(Visitor::COOKIE)->assertSee('2 hours after your last visit');
    $this->get('/')->assertSee(route('privacy'))->assertSee(route('cookies'))->assertDontSee('fonts.googleapis.com');
    $this->get('/register')->assertSee(route('privacy'));
});

it('shows the cookie notice until it has been accepted', function () {
    $this->get('/')->assertSee('data-cookie-notice', false);
    $this->withUnencryptedCookie('kai_cookies_seen', '1')->get('/')->assertDontSee('data-cookie-notice', false);
});

it('has an icon for search results and link previews', function () {
    $this->get('/')->assertSee('favicon-48x48.png')->assertSee('og-image.png')->assertSee('"@type":"Organization"', false);
    foreach (['favicon.ico', 'favicon.svg', 'favicon-48x48.png', 'icon-512.png', 'og-image.png', 'site.webmanifest'] as $file) {
        expect(public_path($file))->toBeFile();
    }
});

it('redirects the old site\'s addresses', function () {
    $this->get('/edit.php?id=2')->assertRedirect('/cars/2')->assertStatus(301);
    $this->get('/edit.php')->assertRedirect(route('cars.create'));
    $this->get('/list.php')->assertRedirect('/cars')->assertStatus(301);
    $this->get('/signup.php')->assertRedirect('/register');
});

it('switches language and translates plurals', function () {
    Car::factory()->count(3)->create();

    $this->get('/language/sl')->assertRedirect();
    $this->get('/')->assertSee('<html lang="sl"', false)->assertSee('3 avti v garaži prav zdaj');

    $this->get('/language/hr');
    $this->get('/')->assertSee('3 automobila u garaži upravo sada');

    $this->get('/language/xx')->assertNotFound();
});

it('sends premium buyers to the checkout, which needs an account', function () {
    $this->get(route('checkout.create', ['product' => 'seller', 'billing' => 'monthly']))->assertRedirect('/login');
    $this->post(route('checkout.store'), ['product' => 'seller', 'method' => 'creditcard'])->assertRedirect('/login');
    $this->get('/premium')->assertSee(route('checkout.create'), false);
});
