<?php

use App\Models\Car;
use App\Services\Assistant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

// Ollama's streamed answer: one JSON object per line
function ndjson(array $chunks): string
{
    return implode("\n", array_map(fn ($c) => json_encode($c, JSON_UNESCAPED_UNICODE), $chunks))."\n";
}

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

it('lets the Ollama model search the cars with tools, then shows those cars as links', function () {
    config(['assistant.driver' => 'auto', 'assistant.ollama.url' => 'http://ollama.test:11434', 'assistant.key' => '']);
    $golf = Car::factory()->create(['name' => 'VW Golf', 'year' => 2020, 'price' => 18000]);
    Car::factory()->create(['name' => 'Audi A6', 'year' => 2021, 'price' => 41000]);

    Http::fake(['ollama.test:11434/api/chat' => Http::sequence()
        ->push(ndjson([
            ['message' => ['role' => 'assistant', 'content' => '', 'thinking' => 'The visitor wants a cheap car…'], 'done' => false],
            ['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'search_cars', 'arguments' => ['price_to' => 20000]]]]], 'done' => false],
            ['message' => ['role' => 'assistant', 'content' => ''], 'done' => true],
        ]))
        ->push(ndjson([
            ['message' => ['role' => 'assistant', 'content' => 'Imamo VW Golf '], 'done' => false],
            ['message' => ['role' => 'assistant', 'content' => 'iz leta 2020 za 18.000 €.'], 'done' => false],
            ['message' => ['role' => 'assistant', 'content' => ''], 'done' => true],
        ])),
    ]);

    $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => 'avto do 20.000 €?']]])
        ->assertOk()
        ->assertJson(['reply' => 'Imamo VW Golf iz leta 2020 za 18.000 €.', 'links' => [['VW Golf (2020) · 18.000 €', route('cars.show', $golf)]]]);

    Http::assertSentCount(2);
    Http::assertSent(fn ($r) => $r['model'] === 'qwen3:30b-a3b' && $r['stream'] === true && ! isset($r['think']) && $r['tools'][0]['function']['name'] === 'search_cars');
    // the second request carries the tool's answer back to the model
    Http::assertSent(fn ($r) => collect($r['messages'])->contains(fn ($m) => $m['role'] === 'tool' && str_contains($m['content'], 'VW Golf') && ! str_contains($m['content'], 'Audi A6')));
});

it('falls back to the built-in answers when the Ollama server is too slow or down', function () {
    config(['assistant.driver' => 'ollama', 'assistant.ollama.url' => 'http://ollama.test:11434']);
    Http::fake(['ollama.test:11434/*' => fn () => throw new ConnectionException('timed out')]);

    $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => 'premium']]])
        ->assertOk()->assertJsonFragment(['links' => [['Premium', route('premium.index')]]]);
});

it('understands a question in any language when it names a make and a price', function () {
    config(['assistant.driver' => 'builtin']);
    Car::factory()->create(['name' => 'Audi A4 Avant', 'year' => 2020, 'price' => 27400]);
    Car::factory()->create(['name' => 'Audi Q7', 'year' => 2022, 'price' => 52000]);
    Car::factory()->create(['name' => 'Škoda Octavia', 'year' => 2019, 'price' => 19500]);
    $ask = fn (string $q) => $this->postJson('/assistant', ['messages' => [['role' => 'user', 'content' => $q]]])->json('links');

    expect($ask('Imate kak Audi do 30.000 €?'))->toHaveCount(1)
        ->and($ask('Imate kak Audi do 30.000 €?')[0][0])->toContain('Audi A4')
        ->and($ask('Haben Sie einen Skoda?')[0][0])->toContain('Octavia')
        ->and($ask('iščem octavio ali a4')[0][0] ?? '')->toContain('Audi A4');
});

it('streams the answer to the chat window as the model writes it', function () {
    config(['assistant.driver' => 'ollama', 'assistant.ollama.url' => 'http://ollama.test:11434']);
    $golf = Car::factory()->create(['name' => 'VW Golf', 'year' => 2020, 'price' => 18000]);
    Http::fake(['ollama.test:11434/api/chat' => Http::sequence()
        ->push(ndjson([['message' => ['content' => '', 'tool_calls' => [['function' => ['name' => 'search_cars', 'arguments' => ['query' => 'golf']]]]], 'done' => true]]))
        ->push(ndjson([['message' => ['content' => 'Golf je '], 'done' => false], ['message' => ['content' => 'na voljo.'], 'done' => true]])),
    ]);

    $response = $this->post('/assistant', ['messages' => [['role' => 'user', 'content' => 'golf?']]], ['Accept' => 'application/x-ndjson']);
    $response->assertOk()->assertHeader('Content-Type', 'application/x-ndjson')->assertHeader('X-Accel-Buffering', 'no');
    $events = collect(explode("\n", trim($response->streamedContent())))->map(fn ($l) => json_decode($l, true));

    expect($events->pluck('type')->all())->toBe(['status', 'status', 'status', 'delta', 'delta', 'done'])
        ->and($events->where('type', 'delta')->pluck('text')->implode(''))->toBe('Golf je na voljo.')
        ->and($events->last()['links'])->toBe([['VW Golf (2020) · 18.000 €', route('cars.show', $golf)]]);
});

it('streams the built-in answer in one piece when the model is down', function () {
    config(['assistant.driver' => 'ollama', 'assistant.ollama.url' => 'http://ollama.test:11434']);
    Http::fake(['ollama.test:11434/*' => fn () => throw new ConnectionException('down')]);

    $events = collect(explode("\n", trim($this->post('/assistant', ['messages' => [['role' => 'user', 'content' => 'premium']]], ['Accept' => 'application/x-ndjson'])->streamedContent())))
        ->map(fn ($l) => json_decode($l, true));
    expect($events->last()['type'])->toBe('done')->and($events->last()['links'][0][1])->toBe(route('premium.index'));
});
