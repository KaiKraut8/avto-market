<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CarBoostController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\CarDealController;
use App\Http\Controllers\CarInquiryController;
use App\Http\Controllers\CarPartController;
use App\Http\Controllers\CarPhotoController;
use App\Http\Controllers\CarWatchController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\MostWatchedController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PremiumController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

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
    Route::post('/cars/{car}/parts', [CarPartController::class, 'store'])->name('cars.parts.store')->can('update', 'car');
    Route::delete('/cars/{car}/parts/{part}', [CarPartController::class, 'destroy'])->name('cars.parts.destroy')->can('update', 'car')->scopeBindings();
    Route::post('/cars/{car}/boost', CarBoostController::class)->name('cars.boost')->can('update', 'car');
    Route::post('/cars/{car}/deal', [CarDealController::class, 'store'])->name('cars.deal.store')->can('runDeal', 'car');
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

// information pages
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
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
