<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RiderService;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    public function __construct(private RiderService $riderService)
    {
    }

    /**
     * POST /api/rider/availability
     * Body: { is_online: bool, is_available: bool }
     */
    public function setAvailability(Request $request)
    {
        $data = $request->validate([
            'is_online'    => ['required', 'boolean'],
            'is_available' => ['required', 'boolean'],
        ]);

        $rider = $request->user();

        $this->riderService->setOnline($rider, $data['is_online']);
        $profile = $this->riderService->setAvailability($rider, $data['is_available']);

        return response()->json([
            'message' => 'Availability updated.',
            'data'    => [
                'is_online'        => $profile->is_online,
                'is_available'     => $profile->is_available,
                'last_location_at' => $profile->last_location_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * PATCH /api/rider/location
     * Body: { lat: float, lng: float }
     */
    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $profile = $this->riderService->updateLocation(
            $request->user(),
            (float) $data['lat'],
            (float) $data['lng']
        );

        return response()->json([
            'message' => 'Location updated.',
            'data'    => [
                'current_lat'      => $profile->current_lat,
                'current_lng'      => $profile->current_lng,
                'last_location_at' => $profile->last_location_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/rider/status
     * Current rider availability state.
     */
    public function status(Request $request)
    {
        $profile = $this->riderService->getOrCreateProfile($request->user());

        return response()->json([
            'data' => [
                'is_online'        => $profile->is_online,
                'is_available'     => $profile->is_available,
                'current_lat'      => $profile->current_lat,
                'current_lng'      => $profile->current_lng,
                'last_location_at' => $profile->last_location_at?->toIso8601String(),
                'has_fresh_location' => $profile->hasFreshLocation(),
                'is_eligible'      => $profile->isEligibleForDispatch(),
            ],
        ]);
    }
}