<?php

namespace App\Providers;

use App\Models\WishlistItem;
use App\Support\Visitor;
use App\View\Composers\NavComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('components.layouts.app', NavComposer::class);

        // what this browser saved before logging in becomes the account's, so deal alerts can reach it
        Event::listen(Login::class, fn (Login $event) => WishlistItem::where('visitor_id', Visitor::id())
            ->whereNull('user_id')->update(['user_id' => $event->user->getAuthIdentifier()]));

        // @price($car->price) → "41.000 €" (or "Price on request"), @eur(44.59) → "44,59 €"
        Blade::directive('price', fn (string $expr) => "<?php echo e(\\App\\Support\\Money::price($expr)); ?>");
        Blade::directive('eur', fn (string $expr) => "<?php echo e(\\App\\Support\\Money::eur($expr)); ?>");
    }
}
