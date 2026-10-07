<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarInquiry;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Support\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// A buyer contacts the seller: their own details have to be valid before the seller's are shown
class CarInquiryController extends Controller
{
    public function __invoke(Request $request, Car $car): RedirectResponse
    {
        abort_unless($car->sellerContact(), 404);

        $data = $request->validateWithBag('inquiry', [
            'buyer_name' => ['required', new PersonName],
            'buyer_email' => ['required', 'email:rfc', 'max:120'],
            'buyer_phone' => ['required', new PhoneNumber],
            'buyer_message' => ['nullable', 'string', 'max:2000'],
        ], [
            'buyer_email.email' => __('Enter a valid email address, like name@example.com.'),
            'buyer_email.required' => __('Enter a valid email address, like name@example.com.'),
        ]);

        CarInquiry::create([
            'car_id' => $car->id,
            'visitor_id' => Visitor::id(),
            'name' => $data['buyer_name'],
            'email' => $data['buyer_email'],
            'phone' => $data['buyer_phone'],
            'message' => $data['buyer_message'] ?: null,
        ]);

        return redirect()->to(route('cars.show', $car).'#contact')->with('contacted', true);
    }
}
