<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Cancelling (with a confirmation page) and resuming a premium subscription
class SubscriptionController extends Controller
{
    public function confirmCancel(Request $request, Subscription $subscription): View
    {
        $this->own($request, $subscription);

        return view('subscriptions.cancel', ['subscription' => $subscription]);
    }

    public function cancel(Request $request, Subscription $subscription, Billing $billing): RedirectResponse
    {
        $this->own($request, $subscription);
        $billing->cancel($subscription);

        return redirect()->route('account')->with('status', __('Your subscription is cancelled. You keep :plan until :date and will not be charged again.', [
            'plan' => $subscription->label(), 'date' => $subscription->current_period_end->format('Y-m-d'),
        ]));
    }

    public function resume(Request $request, Subscription $subscription, Billing $billing): RedirectResponse
    {
        $this->own($request, $subscription);
        $billing->resume($subscription);

        return redirect()->route('account')->with('status', __('Welcome back: your subscription will renew on :date.', [
            'date' => $subscription->current_period_end->format('Y-m-d'),
        ]));
    }

    private function own(Request $request, Subscription $subscription): void
    {
        abort_unless((int) $subscription->user_id === (int) $request->user()->id
            && in_array($subscription->status, ['active', 'past_due'], true), 404);
    }
}
