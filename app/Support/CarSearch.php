<?php

namespace App\Support;

use App\Models\Car;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// What the visitor is searching for: free text, make, year and price ranges, country, deals only and the order,
// read from the query string
class CarSearch
{
    public const MIN_YEAR = 1950;

    // The orders the car list can be shown in; "recommended" is premium first, then pushed, then the rest
    public const SORTS = ['recommended', 'price_asc', 'price_desc', 'year_desc', 'year_asc', 'newest', 'popular'];

    public function __construct(
        public readonly string $q = '',
        public readonly ?int $yearFrom = null,
        public readonly ?int $yearTo = null,
        public readonly ?int $priceFrom = null,
        public readonly ?int $priceTo = null,
        public readonly string $make = '',
        public readonly string $country = '',
        public readonly bool $dealsOnly = false,
        public readonly string $sort = 'recommended',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $year = fn (string $key) => self::int($request->query($key), self::MIN_YEAR, self::maxYear());
        $price = fn (string $key) => self::int($request->query($key), 0, 99999999);

        return new self(
            q: trim(mb_substr((string) $request->query('q', ''), 0, 80)),
            yearFrom: $year('year_from'),
            yearTo: $year('year_to'),
            priceFrom: $price('price_from'),
            priceTo: $price('price_to'),
            make: mb_strtolower(trim(mb_substr((string) $request->query('make', ''), 0, 40))),
            country: in_array($request->query('country'), config('countries'), true) ? $request->query('country') : '',
            dealsOnly: $request->boolean('deals'),
            sort: in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'recommended',
        );
    }

    // Anything outside the range, or not a number, is ignored rather than rejected
    private static function int(mixed $value, int $min, int $max): ?int
    {
        $value = is_string($value) ? Money::parse($value) : $value;
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $n = (int) $value;

        return $n >= $min && $n <= $max ? $n : null;
    }

    public static function maxYear(): int
    {
        return (int) date('Y') + 1;   // next year's models are sold from autumn
    }

    public function apply(Builder $query): void
    {
        $query->search($this->q);
        // a reversed range still means "between"
        [$yearFrom, $yearTo] = $this->ordered($this->yearFrom, $this->yearTo);
        [$priceFrom, $priceTo] = $this->ordered($this->priceFrom, $this->priceTo);
        $query->when($yearFrom !== null, fn ($q) => $q->where('cars.year', '>=', $yearFrom))
            ->when($yearTo !== null, fn ($q) => $q->where('cars.year', '<=', $yearTo))
            ->when($priceFrom !== null, fn ($q) => $q->where('cars.price', '>=', $priceFrom))
            ->when($priceTo !== null, fn ($q) => $q->where('cars.price', '<=', $priceTo))
            // the make is the first word of the name; the column's collation ignores case and accents (skoda = Škoda)
            ->when($this->make !== '', fn ($q) => $q->whereRaw("SUBSTRING_INDEX(TRIM(cars.name), ' ', 1) = ?", [$this->make]))
            ->when($this->country !== '', fn ($q) => $q->where('cars.country', $this->country))
            ->when($this->dealsOnly, fn ($q) => $q->whereHas('deals', fn ($d) => $d->where('ends_at', '>', now())));
    }

    // The order of the list; "recommended" keeps premium, then pushed cars first (needs withPlacement / withPeopleCount)
    public function order(Builder $query): void
    {
        match ($this->sort) {
            'price_asc' => $query->orderByRaw('cars.price IS NULL')->orderBy('cars.price'),
            'price_desc' => $query->orderByRaw('cars.price IS NULL')->orderByDesc('cars.price'),
            'year_desc' => $query->orderByRaw('cars.year IS NULL')->orderByDesc('cars.year'),
            'year_asc' => $query->orderByRaw('cars.year IS NULL')->orderBy('cars.year'),
            'newest' => $query->orderByDesc('cars.created_at'),
            'popular' => $query->orderByDesc('people'),
            default => $query->listingOrder(),
        };
        $query->orderBy('cars.id');
    }

    public function isSorted(): bool
    {
        return $this->sort !== 'recommended';
    }

    private function ordered(?int $a, ?int $b): array
    {
        return $a !== null && $b !== null && $a > $b ? [$b, $a] : [$a, $b];
    }

    public function hasFilters(): bool
    {
        return $this->yearFrom !== null || $this->yearTo !== null || $this->priceFrom !== null || $this->priceTo !== null
            || $this->make !== '' || $this->country !== '' || $this->dealsOnly;
    }

    // How many filters are set, for the badge on the Filters button (the order counts too)
    public function activeCount(): int
    {
        return count(array_filter([
            $this->make !== '', $this->yearFrom !== null || $this->yearTo !== null, $this->priceFrom !== null || $this->priceTo !== null,
            $this->country !== '', $this->dealsOnly, $this->isSorted(),
        ]));
    }

    public function isEmpty(): bool
    {
        return $this->q === '' && ! $this->hasFilters();
    }

    // Short labels for the active filters, e.g. "BMW", "2018–2022", "up to 20.000 €"
    public function labels(array $makes = []): array
    {
        $labels = [];
        if ($this->make !== '') {
            $labels[] = $makes[$this->make] ?? mb_convert_case($this->make, MB_CASE_TITLE);
        }
        [$yf, $yt] = $this->ordered($this->yearFrom, $this->yearTo);
        [$pf, $pt] = $this->ordered($this->priceFrom, $this->priceTo);
        if ($yf !== null && $yt !== null) {
            $labels[] = $yf === $yt ? (string) $yf : "{$yf}–{$yt}";
        } elseif ($yf !== null) {
            $labels[] = __('from :year', ['year' => $yf]);
        } elseif ($yt !== null) {
            $labels[] = __('up to :year', ['year' => $yt]);
        }
        if ($pf !== null && $pt !== null) {
            $labels[] = Money::price($pf).' – '.Money::price($pt);
        } elseif ($pf !== null) {
            $labels[] = __('from :price', ['price' => Money::price($pf)]);
        } elseif ($pt !== null) {
            $labels[] = __('up to :price', ['price' => Money::price($pt)]);
        }

        if ($this->country !== '') {
            $labels[] = __($this->country);
        }
        if ($this->dealsOnly) {
            $labels[] = __('on a deal');
        }

        return $labels;
    }

    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->q, 'year_from' => $this->yearFrom, 'year_to' => $this->yearTo,
            'price_from' => $this->priceFrom, 'price_to' => $this->priceTo, 'make' => $this->make, 'country' => $this->country,
            'deals' => $this->dealsOnly ? 1 : null, 'sort' => $this->isSorted() ? $this->sort : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    // The years and prices of the cars for sale, for the search form's choices and placeholders
    public static function bounds(): array
    {
        $row = Car::query()->forSale()->selectRaw('MIN(year) AS year_min, MAX(year) AS year_max, MIN(price) AS price_min, MAX(price) AS price_max')->first();

        return [
            'yearMin' => (int) ($row->year_min ?: date('Y') - 15),
            'yearMax' => max((int) ($row->year_max ?: date('Y')), (int) date('Y')),
            'priceMin' => $row->price_min !== null ? (int) $row->price_min : null,
            'priceMax' => $row->price_max !== null ? (int) $row->price_max : null,
            // makes (first word of the name) and countries of the cars for sale: [key => label], A–Z
            'makes' => Car::query()->forSale()->pluck('name')
                ->map(fn ($name) => strtok(trim($name), ' ') ?: '')->filter()
                ->groupBy(fn ($make) => mb_strtolower($make))
                ->map(fn ($same) => $same->countBy()->sortDesc()->keys()->first())   // the most common spelling
                ->map(fn ($make) => mb_strlen($make) <= 3 ? mb_strtoupper($make) : mb_convert_case($make, MB_CASE_TITLE))
                ->sort(fn ($a, $b) => strcoll(Str::ascii($a), Str::ascii($b)))->all(),
            'countries' => Car::query()->forSale()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country')->all(),
        ];
    }
}
