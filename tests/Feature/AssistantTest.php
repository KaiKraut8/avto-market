<?php

use App\Models\Car;
use App\Services\Assistant;
use Illuminate\Support\Facades\Http;

it('shows the assistant on every page', function () {
    $this->get('/')->assertSee('data-assistant', false)->assertSee('Welcome. How can I help you today?');
});

it('answers common questions and finds cars without an API key', function () {
    config(['assistant.key' => '']);
    Car::factory()->create(['name' => 'Audi A4', 'year' => 2019, 'price' => 24000]);
    Car::factory()->create(['name' => 'Audi Q7', 'year' => 2021, 'price' => 52000]);

    $ask = fn (string $q) => $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => $q]]])->assertOk()->json();

    expect($ask('how does buying work?')['reply'])->toContain('5%')
        ->and($ask('audi under 30000')['links'])->toHaveCount(1)
        ->and($ask('audi under 30000')['links'][0][0])->toContain('Audi A4')
        ->and($ask('audi from 2020')['links'][0][0])->toContain('Audi Q7')
        ->and($ask('lamborghini')['reply'])->toContain("couldn't find")
        ->and($ask('premium')['links'][0][1])->toBe(route('premium.index'));
});

it('asks Claude when an API key is set, telling it about the cars', function () {
    config(['assistant.key' => 'sk-test', 'assistant.model' => 'claude-sonnet-5-5']);
    Car::factory()->create(['name' => 'Škoda Octavia', 'year' => 2018, 'price' => 15000]);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'The Škoda is 15.000 €.']]])]);

    $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => 'how much is the skoda?']]])
        ->assertOk()->assertJson(['reply' => 'The Škoda is 15.000 €.']);

    Http::assertSent(fn ($request) => $request['model'] === 'claude-sonnet-5-5'
        && str_contains($request['system'], 'Škoda Octavia | 2018 | 15.000 €')
        && $request['messages'] === [['role' => 'user', 'content' => 'how much is the skoda?']]
        && $request->hasHeader('x-api-key', 'sk-test'));
});

it('falls back to the built-in answers when Claude fails, and validates the input', function () {
    config(['assistant.key' => 'sk-test']);
    Http::fake(['api.anthropic.com/*' => Http::response('nope', 500)]);
    $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => 'premium']]])->assertOk()->assertJsonFragment(['links' => [['Premium', route('premium.index')]]]);

    $this->postJson('/assistant', ['messages' => []])->assertStatus(422);
    $this->postJson('/assistant', ['messages' => [['role' => 'system', 'content' => 'x']]])->assertStatus(422);
    expect(app(Assistant::class)->reply([['role' => 'assistant', 'content' => 'only me']])['reply'])->toContain('Hello');
});
