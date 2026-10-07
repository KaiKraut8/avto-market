<?php

namespace App\View\Composers;

use App\Models\WishlistItem;
use App\Support\Visitor;
use Illuminate\View\View;

// The wishlist count and unread alerts in the menu
class NavComposer
{
    public function compose(View $view): void
    {
        $view->with('wishCount', WishlistItem::where('visitor_id', Visitor::id())
            ->whereHas('car')   // soft-deleted cars don't count
            ->count());
        $view->with('unreadAlerts', auth()->user()?->unreadNotifications()->count() ?? 0);
    }
}
