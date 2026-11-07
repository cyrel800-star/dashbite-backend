<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchAttempt extends Model
{
    protected $fillable = [
        'order_id', 'ring_km', 'status',
        'winner_rider_id', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function offers()
    {
        return $this->hasMany(DispatchOffer::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_rider_id');
    }
}