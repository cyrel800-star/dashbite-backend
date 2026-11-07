<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RiderProfile extends Model
{
    protected $fillable = [
        'user_id',
        'is_online',
        'is_available',
        'current_lat',
        'current_lng',
        'last_location_at',
    ];

    protected $casts = [
        'is_online'        => 'boolean',
        'is_available'     => 'boolean',
        'current_lat'      => 'decimal:7',
        'current_lng'      => 'decimal:7',
        'last_location_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * W5.01: Is location fresh within threshold?
     * Default freshness: 5 minutes (configurable via config).
     */
    public function hasFreshLocation(int $thresholdSeconds = 300): bool
    {
        if ($this->current_lat === null || $this->current_lng === null || ! $this->last_location_at) {
            return false;
        }

        return $this->last_location_at->diffInSeconds(now()) <= $thresholdSeconds;
    }

    /**
     * W5.01: Eligible for dispatch?
     */
    public function isEligibleForDispatch(int $thresholdSeconds = 300): bool
    {
        return $this->user?->isApproved()
            && $this->is_online
            && $this->is_available
            && $this->hasFreshLocation($thresholdSeconds);
    }
}