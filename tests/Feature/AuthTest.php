<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('registers a seller with valid contact details', function () {
    $this->post('/register', [
        'name' => 'Ana Kovač', 'email' => 'Ana@Example.com', 'phone' => '+386 40 111 222',
        'password' => 'secret123', 'password_confirmation' => 'secret123',
        'location' => 'Celje', 'country' => 'Slovenia',
    ])->assertRedirect('/account');

    $user = User::firstWhere('email', 'ana@example.com');   // stored in lower case
    expect($user)->not->toBeNull()->and($user->phone)->toBe('+386 40 111 222');
    $this->assertAuthenticatedAs($user);
});

it('rejects unrealistic sign up details and duplicate emails', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', [
        'name' => 'X1', 'email' => 'TAKEN@example.com', 'phone' => '12',
        'password' => 'short', 'password_confirmation' => 'short', 'country' => 'Atlantis',
    ])->assertSessionHasErrors(['name', 'email', 'phone', 'password', 'country']);

    $this->assertGuest();
});

it('logs in, logs out only by POST, and throttles guessing', function () {
    $user = User::factory()->create(['email' => 'seller@example.com', 'password' => 'secret123']);

    $this->post('/login', ['email' => 'seller@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->post('/login', ['email' => 'seller@example.com', 'password' => 'secret123'])->assertRedirect('/account');
    $this->assertAuthenticatedAs($user);

    $this->get('/logout')->assertStatus(405);
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'seller@example.com', 'password' => 'wrong']);
    }
    $this->post('/login', ['email' => 'seller@example.com', 'password' => 'wrong'])->assertStatus(429);
});

it('accepts passwords hashed by the old site', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    // the old site stored password_hash(..., PASSWORD_DEFAULT): plain bcrypt
    DB::table('users')->where('id', $user->id)->update(['password' => password_hash('legacy-pass', PASSWORD_DEFAULT)]);

    $this->post('/login', ['email' => 'old@example.com', 'password' => 'legacy-pass'])->assertRedirect('/account');
    $this->assertAuthenticatedAs($user);
});

it('updates the profile but never the login email', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);

    $this->actingAs($user)->put('/user/profile-information', [
        'name' => 'Novo Ime', 'phone' => '+386 41 999 888', 'location' => 'Koper', 'country' => 'Croatia', 'email' => 'hacker@example.com',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('Novo Ime')->and($user->email)->toBe('me@example.com')->and($user->country)->toBe('Croatia');
});

it('sends a password reset link', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'forgot@example.com']);

    $this->post('/forgot-password', ['email' => 'forgot@example.com'])->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);
});
