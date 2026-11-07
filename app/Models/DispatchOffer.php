<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchOffer extends Model
{
    protected $fillable = [
        'dispatch_attempt_id', 'order_id', 'rider_id',
        'status', 'offered_at', 'responded_at',
    ];

    protected $casts = [
        'offered_at'    => 'datetime',
        'responded_at'  => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(DispatchAttempt::class, 'dispatch_attempt_id');
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}