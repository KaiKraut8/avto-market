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
            default => ['icon' => 'bell', 'title' => __('Notification'), 'text' => '', 'url' => $url],
        };
    }
}
