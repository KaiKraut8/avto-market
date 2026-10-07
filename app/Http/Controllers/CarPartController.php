<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarPart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CarPartController extends Controller
{
    public function store(Request $request, Car $car): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $car->parts()->create($data);   // linked to its category when the name matches one

        return redirect()->route('cars.show', $car)->with('status', __('Part added.'));
    }

    public function destroy(Car $car, CarPart $part): RedirectResponse
    {
        $part->delete();

        return redirect()->route('cars.show', $car)->with('status', __('Part removed.'));
    }
}
