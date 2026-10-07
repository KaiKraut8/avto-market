<?php

namespace App\Providers;

use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Payments\MollieGateway;
use App\Services\Payments\MollieTreasury;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\TestGateway;
use App\Services\Payments\TestTreasury;
use App\Services\Payments\Treasury;
use App\Support\Visitor;
use App\View\Composers\NavComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mollie with an API key; otherwise local test payments, which a production site must never use
        $this->app->singleton(PaymentGateway::class, function () {
            if (config('payments.driver') === 'mollie') {
                return new MollieGateway((string) config('payments.mollie.key'), config('payments.mollie.api'));
            }
            if ($this->app->environment('production')) {
                throw new RuntimeException('Payments need MOLLIE_KEY in production.');
            }

            return new TestGateway;
        });

        // the admin's balance and withdrawals: Mollie with an access token, otherwise simulated from test payments
        $this->app->singleton(Treasury::class, function () {
            if (config('payments.driver') === 'mollie') {
                $token = config('payments.mollie.access_token');
                if (! $token) {
                    throw new RuntimeException('The admin balance needs MOLLIE_ACCESS_TOKEN.');
                }

                return new MollieTreasury($token, config('payments.mollie.api'));
            }
            if ($this->app->environment('production')) {
                throw new RuntimeException('Payments need MOLLIE_KEY in production.');
            }

            return new TestTreasury;
        });

    }

    public function boot(): void
    {
        View::composer('components.layouts.app', NavComposer::class);

        // the marketplace's own account: earnings, withdrawals, every car
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // what this browser saved before logging in becomes the account's, so deal alerts can reach it
        Event::listen(Login::class, fn (Login $event) => WishlistItem::where('visitor_id', Visitor::id())
            ->whereNull('user_id')->update(['user_id' => $event->user->getAuthIdentifier()]));

        // @price($car->price) → "41.000 €" (or "Price on request"), @eur(44.59) → "44,59 €"
        Blade::directive('price', fn (string $expr) => "<?php echo e(\\App\\Support\\Money::price($expr)); ?>");
        Blade::directive('eur', fn (string $expr) => "<?php echo e(\\App\\Support\\Money::eur($expr)); ?>");
    }
}
