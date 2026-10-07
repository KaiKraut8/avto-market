<?php

namespace App\Services;

use App\Models\Car;
use App\Support\Visitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Who looked at a car (views, distinct people) and who has its page open right now (watchers).
class ViewTracker
{
    public function record(Car $car): void
    {
        DB::table('car_views')->insert(['car_id' => $car->id, 'visitor_id' => Visitor::id(), 'viewed_at' => now()]);
        $this->ping($car);
    }

    // An open car page checks in every 15 s; the row is created or its last_seen_at refreshed
    public function ping(Car $car): void
    {
        DB::table('car_watchers')->upsert(
            [['car_id' => $car->id, 'visitor_id' => Visitor::id(), 'last_seen_at' => now()]],
            ['car_id', 'visitor_id'],
            ['last_seen_at']
        );
    }

    public function leave(Car $car): void
    {
        DB::table('car_watchers')->where('car_id', $car->id)->where('visitor_id', Visitor::id())->delete();
    }

    public function watching(Car $car): int
    {
        return DB::table('car_watchers')
            ->where('car_id', $car->id)
            ->where('last_seen_at', '>', now()->subSeconds((int) config('pricing.watch_window')))
            ->count();
    }

    /** @return array{total:int, people:int, watching:int, last_viewed:?string, days:array} */
    public function stats(Car $car): array
    {
        $row = DB::table('car_views')->where('car_id', $car->id)
            ->selectRaw('COUNT(*) AS total, COUNT(DISTINCT visitor_id) AS people, MAX(viewed_at) AS last_viewed')
            ->first();

        // views per day for the last 7 days, oldest first, zero-filled
        $byDay = DB::table('car_views')->where('car_id', $car->id)
            ->where('viewed_at', '>=', today()->subDays(6))
            ->selectRaw('DATE(viewed_at) AS d, COUNT(*) AS n')->groupBy('d')
            ->pluck('n', 'd');
        $days = [];
        for ($k = 6; $k >= 0; $k--) {
            $date = today()->subDays($k);
            $days[] = ['date' => $date->toDateString(), 'label' => $date->format('D'), 'n' => (int) ($byDay[$date->toDateString()] ?? 0)];
        }

        return [
            'total' => (int) $row->total,
            'people' => (int) $row->people,
            'watching' => $this->watching($car),
            'last_viewed' => $row->last_viewed,
            'days' => $days,
        ];
    }

    // Every active car with its numbers, most looked-at first
    public function ranking(): Collection
    {
        $window = (int) config('pricing.watch_window');

        return Car::query()
            ->select('cars.*')
            ->with('coverPhoto')
            ->withCount('views as total')
            ->withPeopleCount()
            ->selectSub(
                DB::table('car_watchers')->selectRaw('COUNT(*)')->whereColumn('car_watchers.car_id', 'cars.id')
                    ->whereRaw("last_seen_at > NOW() - INTERVAL {$window} SECOND"),
                'watching'
            )
            ->selectSub(DB::table('car_views')->selectRaw('MAX(viewed_at)')->whereColumn('car_views.car_id', 'cars.id'), 'last_viewed')
            ->orderByDesc('people')->orderByDesc('total')->orderBy('cars.id')
            ->get();
    }
}
