<?php

namespace App\Http\Controllers;

use App\Services\ReverseGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ReverseGeocodingController extends Controller
{
    public function __invoke(Request $request, ReverseGeocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $address = $geocoder->resolve(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
            );
        } catch (Throwable) {
            $address = null;
        }

        if (! $address) {
            return response()->json([
                'message' => 'A named address could not be found for this location. Please enter it manually.',
            ], 422);
        }

        return response()->json(['address' => $address]);
    }
}
