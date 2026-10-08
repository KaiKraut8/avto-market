<?php

namespace App\Mcp\Tools;

use App\Services\CarCatalog;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('The values the search-cars filters can take right now: the makes and countries of the cars for sale, the range of model years and prices, the sort orders and how many cars there are.')]
#[IsReadOnly]
#[IsIdempotent]
class ListCarFilters extends Tool
{
    public function handle(Request $request, CarCatalog $catalog): Response
    {
        return Response::json($catalog->options());
    }
}
