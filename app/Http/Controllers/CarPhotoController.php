<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarPhoto;
use App\Services\PhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CarPhotoController extends Controller
{
    public function store(Request $request, Car $car, PhotoStore $photos): RedirectResponse
    {
        $request->validate(['photos' => ['required', 'array', 'max:20'], 'photos.*' => ['file']]);
        $result = $photos->store($car, $request->file('photos'));

        return redirect()->route('cars.show', $car)->with('status', CarController::photoMessage($result));
    }

    public function destroy(Car $car, CarPhoto $photo, PhotoStore $photos): RedirectResponse
    {
        $photos->delete($photo);

        return redirect()->route('cars.show', $car)->with('status', __('Photo removed.'));
    }
}
