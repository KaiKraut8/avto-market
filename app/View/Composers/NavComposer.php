<?php

namespace App\View\Composers;

use App\Models\WishlistItem;
use App\Support\Visitor;
use Illuminate\View\View;

// The wishlist count in the menu
class NavComposer
{
    public function compose(View $view): void
    {
        $view->with('wishCount', WishlistItem::where('visitor_id', Visitor::id())
            ->whereHas('car')   // soft-deleted cars don't count
            ->count());
    }
}
