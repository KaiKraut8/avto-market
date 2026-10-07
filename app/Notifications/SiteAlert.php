<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

// An alert in the account's notification list (the bell in the menu).
// Only the kind and its values are stored; the text is written when it is shown, in the reader's language
// (see App\Support\AlertText).
class SiteAlert extends Notification
{
    public const KINDS = ['deal_saved_car', 'deal_for_you', 'search_match', 'car_saved', 'inquiry', 'renewal_due', 'payment_failed',
        'car_reserved', 'reservation_paid', 'sale_canceled', 'sale_completed'];

    public function __construct(public string $kind, public array $values) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind] + $this->values;
    }

    public function databaseType(object $notifiable): string
    {
        return $this->kind;
    }
}
