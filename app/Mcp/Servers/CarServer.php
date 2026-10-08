<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetCar;
use App\Mcp\Tools\ListCarFilters;
use App\Mcp\Tools\SearchCars;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

// KAI Garage's cars over the Model Context Protocol, for AI agents: search, details and the filter values.
// Over HTTP at /mcp (routes/ai.php), or locally with: php artisan mcp:start kai-cars
#[Name('KAI Garage cars')]
#[Version('1.0.0')]
#[Instructions('Finds used cars for sale on KAI Garage (Slovenia). Call list-car-filters to see which makes, countries, years and prices exist, search-cars to find cars matching what the buyer wants, and get-car for the full details of one car. Prices are in EUR. Every car is bought through the site: the buyer pays the commission online to reserve it and the rest to the seller at the handover; give the buyer the car\'s url.')]
class CarServer extends Server
{
    protected array $tools = [
        SearchCars::class,
        GetCar::class,
        ListCarFilters::class,
    ];
}
