<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarSale;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\Treasury;
use App\Support\Money;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

// The marketplace's money: what customers paid, the balance at the payment provider,
// and withdrawals to the bank account. Admin only (php artisan users:admin).
class AdminController extends Controller
{
    public function dashboard(Treasury $treasury): View
    {
        $paid = Payment::where('status', 'paid');
        $byProduct = Payment::where('payments.status', 'paid')
            ->leftJoin('subscriptions', 'subscriptions.id', '=', 'payments.subscription_id')
            ->selectRaw("CASE WHEN payments.purpose = 'boost' THEN 'boost' WHEN payments.purpose IN ('reservation', 'commission') THEN 'commission' ELSE subscriptions.kind END AS product, SUM(payments.amount) AS total, COUNT(*) AS n")
            ->groupBy('product')->get()->keyBy('product');
        $active = Subscription::current()->where('cancel_at_period_end', false)->get();

        try {
            $balance = $treasury->balance();
            $payouts = $treasury->payouts();
            $balanceError = null;
        } catch (Throwable $e) {
            report($e);
            [$balance, $payouts] = [null, []];
            $balanceError = $e instanceof RequestException
                ? __('Mollie did not answer (:status). Check MOLLIE_ACCESS_TOKEN and its scopes.', ['status' => $e->response->status()])
                : $e->getMessage();
        }

        return view('admin.dashboard', [
            'total' => (float) (clone $paid)->sum('amount'),
            'thisMonth' => (float) (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'lastMonth' => (float) (clone $paid)->whereBetween('paid_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()])->sum('amount'),
            'byProduct' => $byProduct,
            'byMethod' => Payment::where('status', 'paid')->selectRaw('method, SUM(amount) AS total, COUNT(*) AS n')->groupBy('method')->get(),
            // monthly recurring revenue: what running subscriptions bring in per month
            'mrr' => round($active->sum(fn (Subscription $s) => $s->price() / $s->months()), 2),
            'activeSellers' => $active->where('kind', 'seller')->count(),
            'activeBuyers' => $active->where('kind', 'buyer')->count(),
            'payments' => Payment::with('user:id,name,email')->latest('id')->limit(25)->get(),
            'balance' => $balance,
            'balanceError' => $balanceError,
            'payouts' => $payouts,
            'openSales' => CarSale::whereIn('status', ['reserved', 'due'])->with(['car', 'seller:id,name', 'buyer:id,name'])->oldest('id')->get(),
            'accounts' => User::count(),
            'cars' => Car::count(),
            'testMode' => config('payments.driver') === 'test',
        ]);
    }

    public function withdrawForm(Treasury $treasury): View|RedirectResponse
    {
        try {
            $balance = $treasury->balance();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.dashboard')->with('error', __('The balance could not be loaded, so nothing can be withdrawn right now.'));
        }

        return view('admin.withdraw', ['balance' => $balance, 'testMode' => config('payments.driver') === 'test']);
    }

    // Withdraw to the bank account set at the payment provider. Behind a fresh password confirmation.
    public function withdraw(Request $request, Treasury $treasury): RedirectResponse
    {
        $request->merge(['amount' => Money::parse($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => __('Tick the box to confirm the withdrawal.')]);

        try {
            $available = $treasury->balance()['available'];
            $amount = isset($data['amount']) ? round((float) $data['amount'], 2) : null;
            if (($amount ?? $available) > $available || $available < 1) {
                return back()->with('error', __('There isn\'t enough on the balance: :available available.', ['available' => Money::eur($available)]));
            }
            $result = $treasury->payout($amount);
        } catch (RequestException $e) {
            report($e);

            return back()->with('error', __('Mollie refused the withdrawal: :reason', ['reason' => $e->response->json('detail') ?? $e->response->status()]));
        }

        DB::transaction(fn () => Payout::create([
            'user_id' => $request->user()->id,
            'provider' => config('payments.driver'),
            'provider_id' => $result['id'],
            'amount' => $result['amount'],
            'status' => $result['status'],
        ]));

        return redirect()->route('admin.dashboard')->with('status', __('Withdrawal of :amount requested. It reaches your bank account on the next business day.', [
            'amount' => Money::eur($result['amount']),
        ]));
    }
}
