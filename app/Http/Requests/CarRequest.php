<?php

namespace App\Http\Requests;

use App\Services\Pricing;
use App\Support\CarSearch;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Posting a new car and editing one share these rules
class CarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // route middleware and CarPolicy decide who may post or edit
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['price' => Money::parse($this->input('price'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'year' => ['nullable', 'integer', 'min:'.CarSearch::MIN_YEAR, 'max:'.CarSearch::maxYear()],
            'location' => ['required', 'string', 'min:2', 'max:80'],
            'country' => ['required', Rule::in(config('countries'))],
            'description' => ['nullable', 'string', 'max:5000'],
            // only when posting a new car
            'plan' => ['sometimes', Rule::in(['free', 'boost', 'premium'])],
            // a new car: the seller agrees to the commission on its sale
            'terms' => [$this->isMethod('post') ? 'accepted' : 'nullable'],
            'billing' => ['sometimes', Rule::in(array_keys(Pricing::PLANS))],
            'photos' => ['sometimes', 'array', 'max:20'],
            'photos.*' => ['file'],   // contents are checked by PhotoStore
        ];
    }

    public function messages(): array
    {
        return [
            'location.required' => __('Location is required: the town or city where the car is (2 to 80 characters).'),
            'location.min' => __('Location is required: the town or city where the car is (2 to 80 characters).'),
            'country.in' => __('Choose the country where the car is listed.'),
            'price.numeric' => __('Price must be a number.'),
            'year.integer' => __('Year must be a number, e.g. 2019.'),
            'year.min' => __('Year must be between :min and :max.', ['min' => CarSearch::MIN_YEAR, 'max' => CarSearch::maxYear()]),
            'year.max' => __('Year must be between :min and :max.', ['min' => CarSearch::MIN_YEAR, 'max' => CarSearch::maxYear()]),
            'terms.accepted' => __('Please confirm that you have read how buying works.'),
        ];
    }
}
