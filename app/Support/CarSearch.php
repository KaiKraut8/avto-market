<?php

namespace App\Support;

use App\Models\Car;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

// What the visitor is searching for: free text plus year and price ranges, read from the query string
class CarSearch
{
    public const MIN_YEAR = 1950;

    public function __construct(
        public readonly string $q = '',
        public readonly ?int $yearFrom = null,
        public readonly ?int $yearTo = null,
        public readonly ?int $priceFrom = null,
        public readonly ?int $priceTo = null,
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
            ->when($priceTo !== null, fn ($q) => $q->where('cars.price', '<=', $priceTo));
    }

    private function ordered(?int $a, ?int $b): array
    {
        return $a !== null && $b !== null && $a > $b ? [$b, $a] : [$a, $b];
    }

    public function hasFilters(): bool
    {
        return $this->yearFrom !== null || $this->yearTo !== null || $this->priceFrom !== null || $this->priceTo !== null;
    }

    public function isEmpty(): bool
    {
        return $this->q === '' && ! $this->hasFilters();
    }

    // Short labels for the active filters, e.g. "2018–2022", "up to 20.000 €"
    public function labels(): array
    {
        $labels = [];
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

        return $labels;
    }

    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->q, 'year_from' => $this->yearFrom, 'year_to' => $this->yearTo,
            'price_from' => $this->priceFrom, 'price_to' => $this->priceTo,
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
        ];
    }
}
