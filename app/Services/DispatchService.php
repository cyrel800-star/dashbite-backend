<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DispatchAttempt;
use App\Models\DispatchOffer;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    public const RING_RADIUS_KM = 1.0;
    public const MAX_RINGS      = 5; // service boundary example (5km from restaurant)

    /**
     * Start dispatch for an order (initial 1 km ring).
     */
    public function start(Order $order): DispatchAttempt
    {
        return $this->blast($order, 1);
    }

    /**
     * Broadcast to all eligible riders within N km ring.
     */
    public function blast(Order $order, int $ringKm): DispatchAttempt
    {
        $restaurant = $order->restaurant;

        // Restaurant coordinates (fallback: 0,0 — could be from service_boundary)
        $originLat = $restaurant->service_boundary['lat_center'] ?? 0;
        $originLng = $restaurant->service_boundary['lng_center'] ?? 0;

        $attempt = DispatchAttempt::create([
            'order_id'   => $order->id,
            'ring_km'    => $ringKm,
            'status'     => 'broadcasting',
            'started_at' => now(),
        ]);

        $eligible = $this->eligibleRidersWithin($originLat, $originLng, $ringKm);

        foreach ($eligible as $rider) {
            DispatchOffer::create([
                'dispatch_attempt_id' => $attempt->id,
                'order_id'            => $order->id,
                'rider_id'            => $rider->user_id,
                'status'              => 'pending',
                'offered_at'          => now(),
            ]);
        }

        return $attempt->fresh(['offers']);
    }

    /**
     * Eligible riders within N km. Uses haversine distance.
     */
    public function eligibleRidersWithin(float $lat, float $lng, int $ringKm): \Illuminate\Support\Collection
    {
        $threshold = now()->subSeconds(RiderService::LOCATION_FRESHNESS_SECONDS);

        $profiles = RiderProfile::query()
            ->where('is_online', true)
            ->where('is_available', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->where('last_location_at', '>=', $threshold)
            ->whereHas('user', fn ($q) => $q->where('role', 'rider')->where('account_status', 'approved'))
            ->get();

        return $profiles->filter(function (RiderProfile $p) use ($lat, $lng, $ringKm) {
            return $this->distanceKm($lat, $lng, (float) $p->current_lat, (float) $p->current_lng) <= $ringKm;
        })->values();
    }

    /**
     * W6.01 Race-safe accept — only ONE rider wins, atomic update.
     */
    public function accept(DispatchOffer $offer, User $rider): bool
    {
        if ($offer->rider_id !== $rider->id) {
            return false;
        }

        if ($offer->status !== 'pending') {
            return false; // already closed or expired
        }

        $attempt = $offer->attempt;

        return DB::transaction(function () use ($offer, $attempt, $rider) {
            // Lock the attempt row
            $locked = DispatchAttempt::lockForUpdate()->find($attempt->id);

            if ($locked->status !== 'broadcasting' || $locked->winner_rider_id !== null) {
                // Someone else won already
                $offer->update(['status' => 'closed', 'responded_at' => now()]);
                return false;
            }

            // Win!
            $locked->update([
                'status'          => 'assigned',
                'winner_rider_id' => $rider->id,
                'ended_at'        => now(),
            ]);

            // Close winning offer
            $offer->update(['status' => 'accepted', 'responded_at' => now()]);

            // Close all other offers in this attempt
            DispatchOffer::where('dispatch_attempt_id', $attempt->id)
                ->where('id', '!=', $offer->id)
                ->where('status', 'pending')
                ->update(['status' => 'closed', 'responded_at' => now()]);

            // Update order + rider
            $order = $locked->order;
            $order->update(['status' => OrderStatus::Accepted]);

            RiderProfile::where('user_id', $rider->id)->update(['is_available' => false]);

            return true;
        });
    }

    /**
     * Haversine distance in kilometers.
     */
    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earthRadius * asin(sqrt($a));
    }
}