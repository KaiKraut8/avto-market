<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// The bell: an account's alerts, newest first. Opening the list marks them as read.
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(100)->get();
        $unread = $notifications->whereNull('read_at')->modelKeys();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications', ['notifications' => $notifications, 'unread' => array_flip($unread), 'user' => $user]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return redirect()->route('notifications.index');
    }
}
