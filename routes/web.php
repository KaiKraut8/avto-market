<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CarBoostController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\CarDealController;
use App\Http\Controllers\CarInquiryController;
use App\Http\Controllers\CarPhotoController;
use App\Http\Controllers\CarSaleController;
use App\Http\Controllers\CarWatchController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\MostWatchedController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PremiumController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WishlistController;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', HomeController::class)->name('home');

// cars: browsing is open to everyone, changing a car needs its seller (CarPolicy)
Route::get('/cars', [CarController::class, 'index'])->name('cars.index');
Route::middleware('auth')->group(function () {
    Route::get('/cars/create', [CarController::class, 'create'])->name('cars.create');
    Route::post('/cars', [CarController::class, 'store'])->name('cars.store');
    Route::put('/cars/{car}', [CarController::class, 'update'])->name('cars.update')->can('update', 'car');
    Route::delete('/cars/{car}', [CarController::class, 'destroy'])->name('cars.destroy')->can('delete', 'car');

    Route::post('/cars/{car}/photos', [CarPhotoController::class, 'store'])->name('cars.photos.store')->can('update', 'car');
    Route::delete('/cars/{car}/photos/{photo}', [CarPhotoController::class, 'destroy'])->name('cars.photos.destroy')->can('update', 'car')->scopeBindings();
    Route::post('/cars/{car}/boost', CarBoostController::class)->name('cars.boost')->can('update', 'car');
    Route::post('/cars/{car}/deal', [CarDealController::class, 'store'])->name('cars.deal.store')->can('runDeal', 'car');
    Route::get('/cars/{car}/sold', [CarSaleController::class, 'soldForm'])->name('cars.sold.create');
    Route::post('/cars/{car}/sold', [CarSaleController::class, 'markSold'])->name('cars.sold');
    Route::post('/sales/{sale}/complete', [CarSaleController::class, 'complete'])->name('sales.complete');
    Route::post('/sales/{sale}/cancel', [CarSaleController::class, 'cancel'])->name('sales.cancel');
    Route::delete('/cars/{car}/deal', [CarDealController::class, 'destroy'])->name('cars.deal.destroy')->can('update', 'car');

    Route::get('/account', AccountController::class)->name('account');

    // payments: choose a method, pay at the provider, come back; subscriptions renew until cancelled
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:10,1');
    Route::get('/checkout/{payment}/return', [CheckoutController::class, 'return'])->name('checkout.return');
    Route::get('/checkout/{payment}/test', [CheckoutController::class, 'test'])->name('checkout.test');
    Route::post('/checkout/{payment}/test', [CheckoutController::class, 'testComplete'])->name('checkout.test.complete');
    Route::get('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'confirmCancel'])->name('subscriptions.cancel.confirm');
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('/subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');

    // the marketplace's own account: earnings, balance, withdrawals
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/withdraw', [AdminController::class, 'withdrawForm'])->name('withdraw.create')->middleware('password.confirm');
        Route::post('/withdraw', [AdminController::class, 'withdraw'])->name('withdraw')->middleware(['password.confirm', 'throttle:5,1']);
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::delete('/notifications', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::delete('/saved-searches/{search}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');
});
Route::get('/cars/{car}', [CarController::class, 'show'])->name('cars.show');
Route::post('/cars/{car}/inquiries', CarInquiryController::class)->name('cars.inquiries.store')->middleware('throttle:10,1');

// live interest: views, who is watching right now
Route::post('/cars/{car}/watch', [CarWatchController::class, 'ping'])->name('cars.watch');
Route::post('/cars/{car}/leave', [CarWatchController::class, 'leave'])->name('cars.leave');
Route::get('/cars/{car}/view-stats', [CarWatchController::class, 'stats'])->name('cars.view-stats');
Route::get('/most-watched', [MostWatchedController::class, 'index'])->name('most-watched');
Route::get('/most-watched/stats', [MostWatchedController::class, 'stats'])->name('most-watched.stats');

Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
Route::post('/wishlist/{car}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

Route::get('/premium', [PremiumController::class, 'index'])->name('premium.index');
Route::get('/deals', DealController::class)->name('deals.index');
Route::post('/webhooks/mollie', MollieWebhookController::class)->name('webhooks.mollie');

// car photos, from the database; no session or cookies, so they can be cached
Route::get('/photos/{photo}', PhotoController::class)->name('photos.show')->whereNumber('photo')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class,
        PreventRequestForgery::class, AddQueuedCookiesToResponse::class,
        EnsureVisitorId::class, SetLocale::class]);

// the chat assistant
Route::post('/assistant', AssistantController::class)->name('assistant')->middleware('throttle:20,1');

// information pages
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/cookies', 'pages.cookies')->name('cookies');
Route::get('/how-buying-works', [PageController::class, 'howBuying'])->name('how-buying');
Route::redirect('/why/documented-parts', '/how-buying-works', 301);   // the parts page was removed
Route::get('/why/{page}', [PageController::class, 'why'])->name('why')->whereIn('page', PageController::WHY_PAGES);

Route::get('/language/{locale}', LanguageController::class)->name('language');

// addresses of the old custom-PHP site, so bookmarks and shared links keep working
Route::permanentRedirect('/index.php', '/');
Route::permanentRedirect('/list.php', '/cars');
Route::permanentRedirect('/views.php', '/most-watched');
Route::permanentRedirect('/wishlist.php', '/wishlist');
Route::permanentRedirect('/premium.php', '/premium');
Route::permanentRedirect('/account.php', '/account');
Route::permanentRedirect('/login.php', '/login');
Route::permanentRedirect('/signup.php', '/register');
Route::permanentRedirect('/cars.php', '/cars');
Route::get('/edit.php', function () {
    $id = filter_var(request('id'), FILTER_VALIDATE_INT);

    return redirect($id ? route('cars.show', $id) : route('cars.create'), 301);
});
