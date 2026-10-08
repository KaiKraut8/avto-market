<?php

use App\Mcp\Servers\CarServer;
use App\Mcp\Tools\GetCar;
use App\Mcp\Tools\ListCarFilters;
use App\Mcp\Tools\SearchCars;
use App\Models\Car;

it('searches cars over MCP with the same filters as the site', function () {
    $a4 = Car::factory()->create(['name' => 'Audi A4', 'year' => 2019, 'price' => 24000, 'country' => 'Slovenia']);
    Car::factory()->create(['name' => 'Audi Q7', 'year' => 2022, 'price' => 52000, 'country' => 'Croatia']);
    Car::factory()->create(['name' => 'Škoda Octavia', 'year' => 2016, 'price' => 9000, 'country' => 'Slovenia']);
    Car::factory()->create(['name' => 'Sold Audi', 'year' => 2020, 'price' => 20000, 'sold_at' => now()]);

    CarServer::tool(SearchCars::class, ['make' => 'audi', 'price_to' => 30000])
        ->assertOk()->assertSee('"total":1')->assertSee('Audi A4')->assertDontSee('Audi Q7')->assertDontSee('Sold Audi');
    CarServer::tool(SearchCars::class, ['make' => 'skoda'])->assertSee('Škoda Octavia');
    CarServer::tool(SearchCars::class, ['sort' => 'price_desc', 'limit' => 2])->assertSee('"total":3')->assertSee('Audi Q7')->assertDontSee('Octavia');
    CarServer::tool(SearchCars::class, ['year_from' => 2021])->assertSee('Audi Q7')->assertDontSee('Audi A4');
    CarServer::tool(SearchCars::class, ['sort' => 'cheapest'])->assertHasErrors();

    CarServer::tool(GetCar::class, ['id' => $a4->id])->assertOk()->assertSee('Audi A4')->assertSee(route('cars.show', $a4), false)->assertSee('how_to_buy');
    CarServer::tool(GetCar::class, ['id' => 99999])->assertHasErrors(['There is no car with id 99999.']);
    CarServer::tool(ListCarFilters::class)->assertOk()->assertSee('"makes":["Audi","Škoda"]')->assertSee('"cars_for_sale":3');
});

it('answers MCP clients over HTTP at /mcp', function () {
    Car::factory()->create(['name' => 'BMW 320d', 'price' => 21000]);

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
        'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1'],
    ]])->assertOk()->assertJsonPath('result.serverInfo.name', 'Vozi cars');

    $tools = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])->assertOk()->json('result.tools');
    expect(collect($tools)->pluck('name')->all())->toBe(['search-cars', 'get-car', 'list-car-filters'])
        ->and($tools[0]['annotations']['readOnlyHint'])->toBeTrue();

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'search-cars', 'arguments' => ['query' => 'bmw']]])
        ->assertOk()->assertSee('BMW 320d');
});
