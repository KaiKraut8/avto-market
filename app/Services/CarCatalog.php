<?php

namespace App\Services;

use App\Models\Car;
use App\Support\CarSearch;
use Illuminate\Http\Request;

// The cars for sale as plain data, for machines: the MCP server and the chat assistant's tools.
// Searching works exactly like the filters on All cars (App\Support\CarSearch).
class CarCatalog
{
    public const MAX_RESULTS = 25;

    /**
     * @param  array{query?: string, make?: string, year_from?: int, year_to?: int, price_from?: int, price_to?: int,
     *               country?: string, deals_only?: bool, sort?: string}  $filters
     * @return array{total: int, cars: array<int, array<string, mixed>>, filters: array<string, mixed>}
     */
    public function search(array $filters, int $limit = 10): array
    {
        // the same rules as the query string of the All cars page, so bad values are ignored the same way
        $search = CarSearch::fromRequest(new Request(array_filter([
            'q' => $filters['query'] ?? null,
            'make' => $filters['make'] ?? null,
            'year_from' => $filters['year_from'] ?? null,
            'year_to' => $filters['year_to'] ?? null,
            'price_from' => $filters['price_from'] ?? null,
            'price_to' => $filters['price_to'] ?? null,
            'country' => $filters['country'] ?? null,
            'deals' => ! empty($filters['deals_only']) ? 1 : null,
            'sort' => $filters['sort'] ?? null,
        ], fn ($v) => $v !== null && $v !== '')));

        $query = Car::query()->forSale()->withPlacement()->withPeopleCount()->with(['coverPhoto', 'activeDeal', 'activeSale'])
            ->tap(fn ($q) => $search->apply($q))
            ->tap(fn ($q) => $search->order($q));
        $total = (clone $query)->count();
        $limit = max(1, min(self::MAX_RESULTS, $limit));

        return [
            'total' => $total,
            'cars' => $query->limit($limit)->get()->map(fn (Car $car) => $this->summary($car))->all(),
            'filters' => array_filter([
                'query' => $search->q ?: null, 'make' => $search->make ?: null, 'year_from' => $search->yearFrom, 'year_to' => $search->yearTo,
                'price_from' => $search->priceFrom, 'price_to' => $search->priceTo, 'country' => $search->country ?: null,
                'deals_only' => $search->dealsOnly ?: null, 'sort' => $search->sort,
            ], fn ($v) => $v !== null),
            'url' => route('cars.index', $search->toQuery()),
        ];
    }

    // One car with everything a buyer would want to know, or null when it isn't for sale
    public function car(int $id): ?array
    {
        $car = Car::query()->withPlacement()->withPeopleCount()->with(['photos', 'activeDeal', 'activeSale', 'user'])->find($id);
        if (! $car) {
            return null;
        }
        $rate = (int) config('pricing.commission_rate');
        $price = $car->activeDeal ? (float) $car->activeDeal->deal_price : ($car->price !== null ? (float) $car->price : null);

        return $this->summary($car) + [
            'description' => $car->description,
            'photos' => $car->photos->map->url()->map(fn ($u) => url($u))->values()->all(),
            'seller' => $car->user?->name ?? $car->legacy_seller_name,
            'listed_at' => $car->created_at?->toDateString(),
            'how_to_buy' => $price !== null && $car->isBuyable()
                ? sprintf('Press "Buy this car" on the car page and pay %d%% online (%.2f EUR); pay the seller the rest (%.2f EUR) at the handover.', $rate, round($price * $rate / 100, 2), round($price * (100 - $rate) / 100, 2))
                : null,
        ];
    }

    // What the filters can be set to right now
    public function options(): array
    {
        $bounds = CarSearch::bounds();

        return [
            'makes' => array_values($bounds['makes']),
            'make_values' => array_keys($bounds['makes']),
            'countries' => $bounds['countries'],
            'years' => ['min' => $bounds['yearMin'], 'max' => $bounds['yearMax']],
            'prices_eur' => ['min' => $bounds['priceMin'], 'max' => $bounds['priceMax']],
            'sorts' => CarSearch::SORTS,
            'cars_for_sale' => Car::query()->forSale()->count(),
        ];
    }

    private function summary(Car $car): array
    {
        return [
            'id' => $car->id,
            'name' => $car->name,
            'make' => $car->make(),
            'year' => $car->year,
            'price_eur' => $car->price !== null ? (float) $car->price : null,
            'deal' => $car->activeDeal ? [
                'price_eur' => (float) $car->activeDeal->deal_price,
                'percent_off' => $car->activeDeal->percentOff(),
                'ends' => $car->activeDeal->ends_at->toDateString(),
            ] : null,
            'location' => $car->locationLabel(),
            'status' => $car->isSold() ? 'sold' : ($car->isReserved() ? 'reserved' : 'for_sale'),
            'premium' => $car->isPremium(),
            'people_interested' => (int) ($car->people ?? 0),
            'photo' => $car->coverPhoto ? url($car->coverPhoto->url()) : null,
            'url' => route('cars.show', $car),
        ];
    }
}
