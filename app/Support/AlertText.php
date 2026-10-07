<?php

namespace App\Support;

use App\Models\SavedSearch;
use Illuminate\Notifications\DatabaseNotification;

// Turns a stored alert into its title, text and link, in the current language
class AlertText
{
    /** @return array{icon:string, title:string, text:string, url:string} */
    public static function for(DatabaseNotification $n): array
    {
        $d = $n->data;
        $car = $d['car_name'] ?? '';
        $search = SavedSearch::describe($d['search_query'] ?? null, $d['search_max'] ?? null);
        $url = isset($d['car_id']) ? route('cars.show', $d['car_id']) : route('account');

        return match ($d['kind'] ?? '') {
            'deal_saved_car' => [
                'icon' => 'tag',
                'title' => __('A car you saved is on a deal'),
                'text' => __(':car is now :price instead of :was, until :date.', [
                    'car' => $car, 'price' => Money::price($d['deal_price']), 'was' => Money::price($d['regular_price']), 'date' => $d['ends_at'],
                ]),
                'url' => $url,
            ],
            'deal_for_you' => [
                'icon' => 'tag',
                'title' => __('A deal you might like'),
                'text' => __(':car is now :price instead of :was, until :date.', [
                    'car' => $car, 'price' => Money::price($d['price']), 'was' => Money::price($d['regular_price']), 'date' => $d['ends_at'],
                ]).' '.(($d['reason'] ?? '') === 'search'
                    ? __('It matches your saved search :search.', ['search' => $search])
                    : __('It is similar to cars in your wishlist.')),
                'url' => $url,
            ],
            'search_match' => [
                'icon' => 'search',
                'title' => __('New car for your saved search'),
                'text' => __(':car (:price) matches your saved search :search.', [
                    'car' => $car, 'price' => Money::price($d['price'] ?? null), 'search' => $search,
                ]),
                'url' => $url,
            ],
            'car_saved' => [
                'icon' => 'heart',
                'title' => __('Someone saved your car'),
                'text' => trans_choice(':car is now in :count wishlist.|:car is now in :count wishlists.', (int) ($d['saves'] ?? 1), ['car' => $car]),
                'url' => $url,
            ],
            'inquiry' => [
                'icon' => 'chat',
                'title' => __('New inquiry about :car', ['car' => $car]),
                'text' => __(':name (:phone, :email) wants to know more.', [
                    'name' => $d['name'] ?? '', 'phone' => $d['phone'] ?? '', 'email' => $d['email'] ?? '',
                ]).(! empty($d['member']) ? ' '.__('They are a premium buyer: your member price applies.') : ''),
                'url' => $url,
            ],
            'renewal_due' => [
                'icon' => 'card',
                'title' => __('Time to renew your premium'),
                'text' => __(':plan ends on :date. paysafecard can\'t be charged automatically, so pay the next period (:price) to keep it.', [
                    'plan' => ($d['plan'] ?? '') === 'buyer' ? __('Premium buyer') : __('Premium seller'),
                    'date' => $d['date'] ?? '', 'price' => Money::eur($d['price'] ?? 0),
                ]),
                'url' => route('checkout.create', ['product' => 'renew', 'subscription' => $d['subscription_id'] ?? 0]),
            ],
            'payment_failed' => [
                'icon' => 'card',
                'title' => __('Your premium renewal payment failed'),
                'text' => __('We could not charge your :plan renewal. We will try again tomorrow; you can also pay now with another method.', [
                    'plan' => ($d['plan'] ?? '') === 'buyer' ? __('Premium buyer') : __('Premium seller'),
                ]),
                'url' => route('checkout.create', ['product' => 'renew', 'subscription' => $d['subscription_id'] ?? 0]),
            ],
            'car_reserved' => [
                'icon' => 'tag',
                'title' => __(':car is reserved: a buyer paid', ['car' => $car]),
                'text' => __(':name (:phone, :email) paid the commission online. Arrange the handover; they pay you :remainder. Then confirm the sale on the car\'s page.', [
                    'name' => $d['name'] ?? '', 'phone' => $d['phone'] ?? '', 'email' => $d['email'] ?? '', 'remainder' => Money::price($d['remainder'] ?? 0),
                ]),
                'url' => $url,
            ],
            'reservation_paid' => [
                'icon' => 'check',
                'title' => __(':car is reserved for you', ['car' => $car]),
                'text' => __('The seller has your details and will contact you. At the handover you pay the seller :remainder.', ['remainder' => Money::price($d['remainder'] ?? 0)]),
                'url' => $url,
            ],
            'sale_canceled' => [
                'icon' => 'card',
                'title' => __('The purchase of :car was cancelled', ['car' => $car]),
                'text' => __('Your :refund is being refunded to the same payment method.', ['refund' => Money::eur($d['refund'] ?? 0)]),
                'url' => $url,
            ],
            'sale_completed' => [
                'icon' => 'check',
                'title' => __('Congratulations on your :car', ['car' => $car]),
                'text' => __('The seller confirmed the handover. Enjoy the drive!'),
                'url' => $url,
            ],
            default => ['icon' => 'bell', 'title' => __('Notification'), 'text' => '', 'url' => $url],
        };
    }
}
