<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RiderService;
use Illuminate\Http\Request;
use App\Models\DispatchOffer;
use App\Services\DispatchService;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLifecycleService;
use Illuminate\Validation\ValidationException;
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
    /**
     * W6.01: Accept a dispatch offer (race-safe).
     */
    public function acceptOffer(Request $request, int $offerId)
    {
        $offer = DispatchOffer::find($offerId);

        if (! $offer || $offer->rider_id !== $request->user()->id) {
            return response()->json(['error' => 'offer_not_found'], 404);
        }

        $won = app(DispatchService::class)->accept($offer, $request->user());

        if (! $won) {
            return response()->json([
                'error'   => 'offer_no_longer_available',
                'message' => 'This offer was already accepted by another rider or expired.',
            ], 409);
        }

        return response()->json([
            'message' => 'Offer accepted.',
            'data'    => [
                'order_id' => $offer->order_id,
                'status'   => 'assigned',
            ],
        ]);
    }
    /**
     * W6.02: Rider marks order as picked up.
     */
    public function markPickedUp(Request $request, int $orderId)
    {
        $order = $this->findRiderOrder($request->user(), $orderId);

        try {
            $order = app(OrderLifecycleService::class)->markPickedUp($order, $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'error'  => 'invalid_transition',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Order marked as picked up.',
            'data'    => $order,
        ]);
    }

    /**
     * W6.02: Rider marks order as delivered + records COD.
     */
    public function markDelivered(Request $request, int $orderId)
    {
        $order = $this->findRiderOrder($request->user(), $orderId);

        $data = $request->validate([
            'cash_collected' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $order = app(OrderLifecycleService::class)->markDelivered(
                $order,
                $request->user(),
                isset($data['cash_collected']) ? (float) $data['cash_collected'] : null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'error'  => 'invalid_transition',
                'errors' => $e->errors(),
            ], 422);
        }

        // Free up rider for next dispatch
        \App\Models\RiderProfile::where('user_id', $request->user()->id)
            ->update(['is_available' => true]);

        return response()->json([
            'message' => 'Order delivered.',
            'data'    => $order,
        ]);
    }

    /**
     * Helper: ensure the order is currently assigned to this rider
     * (via dispatch_attempts winner OR order status).
     */
    private function findRiderOrder(User $rider, int $orderId): Order
    {
        $order = Order::find($orderId);

        if (! $order) {
            abort(response()->json(['error' => 'order_not_found'], 404));
        }

        // Verify the order is currently assigned to this rider via dispatch attempt
        $assignedTo = \App\Models\DispatchAttempt::where('order_id', $order->id)
            ->where('status', 'assigned')
            ->where('winner_rider_id', $rider->id)
            ->exists();

        if (! $assignedTo) {
            abort(response()->json(['error' => 'order_not_assigned_to_you'], 403));
        }

        return $order;
    }
}