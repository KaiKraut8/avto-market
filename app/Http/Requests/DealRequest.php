<?php

namespace App\Http\Requests;

use App\Models\CarDeal;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// A special deal: between 1 % and 50 % off the car's price; the member price lower still
class DealRequest extends FormRequest
{
    public const MAX_OFF = 0.5;

    protected $errorBag = 'deal';

    public function authorize(): bool
    {
        return true;   // the route checks CarPolicy::runDeal
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'deal_price' => Money::parse($this->input('deal_price')),
            'member_price' => Money::parse($this->input('member_price')),
        ]);
    }

    public function rules(): array
    {
        $price = (float) $this->route('car')->price;
        $floor = round($price * (1 - self::MAX_OFF), 2);
        $inRange = function (string $attribute, mixed $value, Closure $fail) use ($price, $floor) {
            if ((float) $value >= $price || (float) $value < $floor) {
                $fail(__('The deal price has to be lower than :price and at least :floor (up to 50% off).', [
                    'price' => Money::price($price), 'floor' => Money::price($floor),
                ]));
            }
        };

        return [
            'deal_price' => ['required', 'numeric', $inRange],
            'member_price' => ['nullable', 'numeric', 'lt:deal_price', $inRange],
            'days' => ['required', 'integer', Rule::in(CarDeal::DURATIONS)],
        ];
    }

    public function messages(): array
    {
        return [
            'deal_price.required' => __('Enter the deal price.'),
            'member_price.lt' => __('The member price has to be lower than the deal price.'),
        ];
    }
}
