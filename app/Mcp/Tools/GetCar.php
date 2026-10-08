<?php

namespace App\Mcp\Tools;

use App\Services\CarCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Full details of one car by its id (from search-cars): description, all photos, seller, status, price and deal, and how to buy it.')]
#[IsReadOnly]
#[IsIdempotent]
class GetCar extends Tool
{
    public function handle(Request $request, CarCatalog $catalog): Response
    {
        $id = (int) $request->validate(['id' => ['required', 'integer', 'min:1']])['id'];
        $car = $catalog->car($id);

        return $car ? Response::json($car) : Response::error("There is no car with id {$id}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The car\'s id.')->required(),
        ];
    }
}
