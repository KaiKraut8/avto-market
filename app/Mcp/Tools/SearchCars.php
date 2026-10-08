<?php

namespace App\Mcp\Tools;

use App\Services\CarCatalog;
use App\Support\CarSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search the used cars for sale on KAI Garage. Every filter is optional and they combine; with none, all cars are listed. Returns the number of matches and up to `limit` cars with id, name, make, year, price (and deal price), location, status, photo and page url.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchCars extends Tool
{
    public function handle(Request $request, CarCatalog $catalog): Response
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:80'],
            'make' => ['nullable', 'string', 'max:40'],
            'year_from' => ['nullable', 'integer'],
            'year_to' => ['nullable', 'integer'],
            'price_from' => ['nullable', 'integer', 'min:0'],
            'price_to' => ['nullable', 'integer', 'min:0'],
            'country' => ['nullable', 'string', 'max:60'],
            'deals_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:'.implode(',', CarSearch::SORTS)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.CarCatalog::MAX_RESULTS],
        ]);

        return Response::json($catalog->search($data, (int) ($data['limit'] ?? 10)));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free text matched in the name, description, location and country, e.g. "Golf" or "Ljubljana".'),
            'make' => $schema->string()->description('Car make, e.g. "audi", "bmw", "škoda" (case and accents don\'t matter). See list-car-filters.'),
            'year_from' => $schema->integer()->description('Oldest model year, e.g. 2018.'),
            'year_to' => $schema->integer()->description('Newest model year.'),
            'price_from' => $schema->integer()->description('Lowest price in EUR.'),
            'price_to' => $schema->integer()->description('Highest price in EUR, e.g. 30000.'),
            'country' => $schema->string()->description('Country the car is in, in English, e.g. "Slovenia".'),
            'deals_only' => $schema->boolean()->description('Only cars whose price is dropped by a special deal right now.'),
            'sort' => $schema->string()->enum(CarSearch::SORTS)->description('Order: recommended (default), price_asc, price_desc, year_desc, year_asc, newest, popular.'),
            'limit' => $schema->integer()->description('How many cars to return, 1 to '.CarCatalog::MAX_RESULTS.' (default 10).'),
        ];
    }
}
