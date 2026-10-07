<?php

namespace App\Providers;

use App\View\Composers\NavComposer;
use Illuminate\Support\Facades\Blade;
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

        // @price($car->price) → "41.000 €" (or "Price on request"), @eur(44.59) → "44,59 €"
        Blade::directive('price', fn (string $expr) => "<?php echo e(\\App\\Support\\Money::price($expr)); ?>");
        Blade::directive('eur', fn (string $expr) => "<?php echo e(\\App\\Support\\Money::eur($expr)); ?>");
    }
}
