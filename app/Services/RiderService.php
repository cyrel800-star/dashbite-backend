<?php

namespace App\Services;

use App\Models\RiderProfile;
use App\Models\User;

class RiderService
{
    public const LOCATION_FRESHNESS_SECONDS = 300; // 5 minutes

    public function getOrCreateProfile(User $rider): RiderProfile
    {
        return RiderProfile::firstOrCreate(
            ['user_id' => $rider->id],
            [
                'is_online'    => false,
                'is_available' => true,
            ]
        );
    }

    public function setOnline(User $rider, bool $online): RiderProfile
    {
        $profile = $this->getOrCreateProfile($rider);
        $profile->update(['is_online' => $online]);

        // Going offline clears availability to prevent stale eligibility
        if (! $online) {
            $profile->update(['is_available' => true]);
        }

        return $profile->fresh();
    }

    public function setAvailability(User $rider, bool $available): RiderProfile
    {
        $profile = $this->getOrCreateProfile($rider);
        $profile->update(['is_available' => $available]);
        return $profile->fresh();
    }

    public function updateLocation(User $rider, float $lat, float $lng): RiderProfile
    {
        $profile = $this->getOrCreateProfile($rider);
        $profile->update([
            'current_lat'      => $lat,
            'current_lng'      => $lng,
            'last_location_at' => now(),
        ]);
        return $profile->fresh();
    }

    /**
     * W5.01: List eligible rider ids for dispatch.
     */
    public function eligibleRiderIds(): array
    {
        $threshold = now()->subSeconds(self::LOCATION_FRESHNESS_SECONDS);

        return RiderProfile::query()
            ->where('is_online', true)
            ->where('is_available', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng')
            ->whereNotNull('last_location_at')
            ->where('last_location_at', '>=', $threshold)
            ->whereHas('user', function ($q) {
                $q->where('role', 'rider')->where('account_status', 'approved');
            })
            ->pluck('user_id')
            ->all();
    }
}