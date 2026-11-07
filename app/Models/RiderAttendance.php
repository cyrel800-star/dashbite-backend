<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderAttendance extends Model
{
    protected $fillable = [
        'rider_id', 'date',
        'first_online_at', 'last_offline_at', 'deliveries_count',
    ];

    protected $casts = [
        'date'            => 'date',
        'first_online_at' => 'datetime',
        'last_offline_at' => 'datetime',
        'deliveries_count' => 'integer',
    ];

    public function rider() { return $this->belongsTo(User::class, 'rider_id'); }
}