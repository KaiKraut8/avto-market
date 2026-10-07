<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Premium buyers save searches and get an alert when a new car or a deal matches
class SavedSearchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasBuyerPremium(), 403);

        $request->merge(['max_price' => Money::parse($request->input('max_price')), 'query' => trim((string) $request->input('query')) ?: null]);
        $data = $request->validateWithBag('search', [
            'query' => ['nullable', 'string', 'max:80', 'required_without:max_price'],
            'max_price' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
        ], [
            'query.required_without' => __('Enter a search, a highest price, or both.'),
        ]);
        if ($user->savedSearches()->count() >= SavedSearch::LIMIT) {
            return back()->with('error', __('You can save up to :count searches. Remove one first.', ['count' => SavedSearch::LIMIT]));
        }
        $search = $user->savedSearches()->create($data);

        return back()->with('status', __('Search saved: :search. We will tell you when a car matches.', ['search' => $search->label()]));
    }

    public function destroy(Request $request, SavedSearch $search): RedirectResponse
    {
        abort_unless((int) $search->user_id === (int) $request->user()->id, 404);
        $search->delete();

        return back()->with('status', __('Saved search removed.'));
    }
}
