<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarPart;
use App\Models\CarPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Information pages: contact, and the three "why buy here" pages from the home page
class PageController extends Controller
{
    public const WHY_PAGES = ['documented-parts', 'real-photos', 'live-interest'];

    public function contact(): View
    {
        $now = company_now();
        $today = config('company.opening_hours')[$now->dayOfWeekIso] ?? null;
        $openNow = $today && $now->format('H:i') >= $today[0] && $now->format('H:i') < $today[1];

        // when we open next, if closed now
        $next = null;
        if (! $openNow) {
            for ($i = 0; $i < 8 && ! $next; $i++) {
                $day = $now->copy()->addDays($i);
                $slot = config('company.opening_hours')[$day->dayOfWeekIso] ?? null;
                if ($slot && ($i > 0 || $now->format('H:i') < $slot[0])) {
                    $next = ['day' => $i === 0 ? __('today') : ($i === 1 ? __('tomorrow') : $day->translatedFormat('l')), 'time' => $slot[0]];
                }
            }
        }

        return view('pages.contact', [
            'openNow' => $openNow,
            'closesAt' => $openNow ? $today[1] : null,
            'next' => $next,
            'week' => collect(config('company.opening_hours'))->map(fn ($slot, $iso) => [
                'day' => now()->startOfWeek()->addDays($iso - 1)->translatedFormat('l'),
                'hours' => $slot ? $slot[0].'–'.$slot[1] : null,
                'today' => $iso === $now->dayOfWeekIso,
            ]),
        ]);
    }

    public function why(string $page): View
    {
        $data = match ($page) {
            'documented-parts' => [
                'parts' => CarPart::whereHas('car')->count(),
                'cars' => Car::count(),
                'categories' => DB::table('car_parts')->join('cars', 'cars.id', '=', 'car_parts.car_id')->whereNull('cars.deleted_at')
                    ->select('car_parts.name', DB::raw('COUNT(*) AS n'))->groupBy('car_parts.name')->orderByDesc('n')->limit(8)->get(),
                'example' => Car::withCount('parts')->with(['parts', 'coverPhoto'])->orderByDesc('parts_count')->first(),
            ],
            'real-photos' => [
                'photos' => CarPhoto::whereHas('car')->count(),
                'cars' => Car::count(),
                'withPhotos' => Car::has('photos')->count(),
                'gallery' => CarPhoto::whereHas('car')->with('car')->latest('id')->limit(6)->get(),
            ],
            'live-interest' => [
                'people' => (int) DB::table('car_views')->join('cars', 'cars.id', '=', 'car_views.car_id')->whereNull('cars.deleted_at')->distinct()->count('car_views.visitor_id'),
                'views' => (int) DB::table('car_views')->join('cars', 'cars.id', '=', 'car_views.car_id')->whereNull('cars.deleted_at')->count(),
                'watching' => (int) DB::table('car_watchers')->where('last_seen_at', '>', now()->subSeconds((int) config('pricing.watch_window')))->count(),
                'top' => Car::withPeopleCount()->with('coverPhoto')->orderByDesc('people')->first(),
            ],
        };

        return view('pages.why-'.$page, $data);
    }
}
