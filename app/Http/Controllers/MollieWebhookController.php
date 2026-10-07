<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Billing;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Mollie calls this when a payment changes. It only sends the payment's id; the status is
// always fetched from Mollie itself, so a forged call can't mark anything as paid.
class MollieWebhookController extends Controller
{
    public function __invoke(Request $request, Billing $billing): Response
    {
        $payment = Payment::where('provider', 'mollie')->where('provider_id', (string) $request->input('id'))->first();
        if ($payment) {
            $billing->sync($payment);
        }

        return response('', 200);   // unknown ids get 200 too, so nobody can probe which exist
    }
}
